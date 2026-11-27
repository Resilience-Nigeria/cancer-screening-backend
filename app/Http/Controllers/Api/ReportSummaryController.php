<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI-assisted interpretation of an uploaded report (breast ultrasound, mammography, biopsy/histology, CBE notes).
 * Returns a short bullet summary and SUGGESTED form values. Nothing is saved: the health worker reviews the
 * suggestions and chooses what to apply. It supports documentation; it does not diagnose.
 *
 *  GET  /ai/status          → { enabled }
 *  POST /ai/report-summary  (multipart: file = JPG/PNG/WebP/PDF, or text; reportType = ultrasound|mammography|biopsy|cbe|other)
 */
class ReportSummaryController extends Controller
{
    private const FIELDS = [
        'leftCbeResult' => ['type' => 'string', 'enum' => ['normal', 'lump_detected', 'lump_not_detected']],
        'rightCbeResult' => ['type' => 'string', 'enum' => ['normal', 'lump_detected', 'lump_not_detected']],
        'physicalChanges' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => [
            'nipple_discharge', 'nipple_retraction', 'skin_discoloration', 'skin_dimpling', 'skin_thickening',
            'skin_ulceration', 'visible_veins', 'asymmetry', 'swelling', 'other',
        ]]],
        'physicalChangesOther' => ['type' => 'string'],
        'breastPain' => ['type' => 'string', 'enum' => ['yes', 'no'], 'description' => 'Breast tenderness/pain'],
        'leftBiradsScore' => ['type' => 'string', 'enum' => ['0', '1', '2', '3', '4', '5', '6']],
        'rightBiradsScore' => ['type' => 'string', 'enum' => ['0', '1', '2', '3', '4', '5', '6']],
        'leftBreastDensity' => ['type' => 'string', 'enum' => ['a', 'b', 'c', 'd']],
        'rightBreastDensity' => ['type' => 'string', 'enum' => ['a', 'b', 'c', 'd']],
        'leftImagingFinding' => ['type' => 'string', 'enum' => ['normal', 'suspicious']],
        'rightImagingFinding' => ['type' => 'string', 'enum' => ['normal', 'suspicious']],
        'biopsyProcedure' => ['type' => 'string', 'enum' => ['fna', 'core_needle', 'vacuum_assisted', 'excision', 'incision', 'punch', 'other']],
        'histologyResult' => ['type' => 'string', 'enum' => ['malignant', 'benign']],
        'ihcResult' => ['type' => 'string', 'description' => 'e.g. ER+/PR+/HER2-'],
    ];

    public function status(): JsonResponse
    {
        return response()->json(['enabled' => $this->enabled()]);
    }

    private function enabled(): bool
    {
        return (bool) config('ncsr_ai.enabled') && !empty(config('ncsr_ai.key'));
    }

    public function summarise(Request $request): JsonResponse
    {
        abort_if(!$this->enabled(), 503, 'AI report summaries are turned off on this server.');
        $maxKb = max(1, (int) config('ncsr_ai.max_mb', 10)) * 1024;
        $data = $request->validate([
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', "max:{$maxKb}"],
            'text' => ['nullable', 'string', 'max:20000'],
            'reportType' => ['nullable', 'in:ultrasound,mammography,biopsy,cbe,other'],
        ]);
        abort_if(!$request->hasFile('file') && empty($data['text']), 422, 'Attach the report (photo or PDF) or paste its text.');

        $content = [];
        if ($request->hasFile('file')) {
            $f = $request->file('file');
            $mime = $f->getMimeType() ?: 'application/octet-stream';
            $b64 = base64_encode(file_get_contents($f->getRealPath()));
            $content[] = $mime === 'application/pdf'
                ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $b64]]
                : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime === 'image/jpg' ? 'image/jpeg' : $mime, 'data' => $b64]];
        }
        $kind = $data['reportType'] ?? 'other';
        $content[] = ['type' => 'text', 'text' => trim(($data['text'] ?? '') . "\n\n" .
            "This is a breast {$kind} report (or clinical notes) for the National Cancer Screening Registry in Nigeria. " .
            'Extract the findings that are actually written in it. Use the record_findings tool. ' .
            'Bullets: short, factual, one finding each, keep measurements and laterality exactly as written (e.g. "Left breast: 6 × 2 cm lump at 2 o’clock"). ' .
            'Only fill a field when the report clearly states it; leave it out otherwise. Never guess a diagnosis that is not written.')];

        try {
            $res = Http::timeout(90)->withHeaders([
                'x-api-key' => config('ncsr_ai.key'),
                'anthropic-version' => '2023-06-01',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => config('ncsr_ai.model'),
                'max_tokens' => 1500,
                'system' => 'You extract findings from breast imaging, pathology and clinical examination reports to help health workers fill a screening form. You do not give diagnoses or advice beyond what the report states.',
                'tools' => [[
                    'name' => 'record_findings',
                    'description' => 'Structured summary of the report.',
                    'input_schema' => [
                        'type' => 'object',
                        'properties' => [
                            'bullets' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Key findings, one per bullet.'],
                            'reportDate' => ['type' => 'string', 'description' => 'YYYY-MM-DD if written'],
                            'fields' => ['type' => 'object', 'properties' => self::FIELDS, 'additionalProperties' => false],
                            'unclear' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Parts that could not be read or are ambiguous.'],
                        ],
                        'required' => ['bullets', 'fields'],
                    ],
                ]],
                'tool_choice' => ['type' => 'tool', 'name' => 'record_findings'],
                'messages' => [['role' => 'user', 'content' => $content]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('AI report summary failed: ' . $e->getMessage());
            abort(502, 'The AI service could not be reached. Try again, or fill the form by hand.');
        }

        if (!$res->successful()) {
            Log::warning('AI report summary error ' . $res->status() . ': ' . mb_substr($res->body(), 0, 500));
            abort(502, 'The AI service could not read this report. Try a clearer photo, or fill the form by hand.');
        }
        $tool = collect($res->json('content') ?? [])->firstWhere('type', 'tool_use');
        $out = $tool['input'] ?? null;
        abort_if(!is_array($out), 502, 'The AI service returned no summary. Try again.');

        // Keep only known fields with allowed values.
        $fields = [];
        foreach ((array) ($out['fields'] ?? []) as $k => $v) {
            $spec = self::FIELDS[$k] ?? null;
            if (!$spec || $v === null || $v === '' || $v === []) continue;
            if (isset($spec['enum']) && !in_array($v, $spec['enum'], true)) continue;
            if (($spec['type'] ?? '') === 'array') $v = array_values(array_intersect((array) $v, $spec['items']['enum']));
            if ($v === []) continue;
            $fields[$k] = $v;
        }

        if (class_exists(ActivityLog::class)) {
            try {
                ActivityLog::record('ai_report_summary', "Used AI to summarise a {$kind} report.", ['subjectType' => 'ai']);
            } catch (\Throwable $e) {
            }
        }

        return response()->json([
            'bullets' => array_values(array_filter(array_map('strval', (array) ($out['bullets'] ?? [])))),
            'reportDate' => $out['reportDate'] ?? null,
            'fields' => $fields,
            'unclear' => array_values((array) ($out['unclear'] ?? [])),
            'disclaimer' => 'AI-generated from the uploaded report. Check every item against the report before saving — it does not replace clinical judgement.',
        ]);
    }
}
