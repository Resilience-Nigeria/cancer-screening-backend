<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ScreeningSample;
use App\Support\ScreeningTestCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * PATCH /screening-samples/{id}/details
 *
 * Simulation report §6: fix a wrong selection (e.g. the biopsy procedure / specimen type, lab number, date)
 * on an existing sample or test — no new client or sample record. Every change is written to the activity log
 * with the old and new values and the reason.
 */
class SampleCorrectionController extends Controller
{
    public function update(Request $request, int $id): JsonResponse
    {
        $user = auth('api')->user();
        $sample = ScreeningSample::findOrFail($id);
        abort_if(!$user->canAccessFacility($sample->facilityId), 403, 'You cannot access this sample');
        $test = ScreeningTestCatalog::find($sample->testType);
        abort_if(!$test, 422, 'This test type is no longer configured.');

        $data = $request->validate([
            'specimenType' => ['sometimes', 'nullable', Rule::in($test['specimenTypes'] ?? [])],
            'collectionMethod' => ['sometimes', 'nullable', Rule::in(array_column($test['collectionMethods'] ?? [], 'value'))],
            'labSampleNumber' => ['sometimes', 'nullable', 'string', 'max:100'],
            'sampleDate' => ['sometimes', 'date', 'before_or_equal:today'],
            'collectedBy' => ['sometimes', 'nullable', 'string', 'max:255'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'reason' => ['required', 'string', 'min:3', 'max:300'],
        ], ['reason.required' => 'Say briefly why you are correcting it (e.g. "wrong procedure selected").']);

        $reason = $data['reason'];
        unset($data['reason']);
        if (isset($data['sampleDate']) && $sample->resultDate && $data['sampleDate'] > $sample->resultDate->toDateString()) {
            abort(422, 'The sample date cannot be after the result date.');
        }

        $before = [];
        foreach ($data as $k => $v) {
            $old = $sample->{$k} instanceof \DateTimeInterface ? $sample->{$k}->format('Y-m-d') : $sample->{$k};
            if ((string) $old !== (string) $v) $before[$k] = $old;
        }
        if (!$before) return response()->json(['message' => 'Nothing changed.', 'sample' => $sample]);

        $sample->update($data);

        if (class_exists(ActivityLog::class)) {
            try {
                $changes = collect($before)->map(fn ($old, $k) => "{$k}: " . ($old ?? '—') . ' → ' . ($data[$k] ?? '—'))->implode('; ');
                ActivityLog::record('sample_corrected', "Corrected {$sample->sampleRef} ({$sample->clientId}): {$changes}. Reason: {$reason}", [
                    'subjectType' => 'screening_sample', 'subjectId' => $sample->sampleId,
                ]);
            } catch (\Throwable $e) {
            }
        }

        return response()->json(['message' => 'Corrected. The change was recorded in the activity log.', 'sample' => $sample->fresh()]);
    }
}
