<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ScreeningSample;
use App\Models\ScreeningVisit;
use App\Support\ScreeningLabSync;
use App\Support\ScreeningTestCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ScreeningSampleController extends Controller
{
    // Client details for the result pop-up, and the facility where the sample was taken / the test was done.
    private const WITH_CLIENT = [
        'client:clientId,fullName,gender,dateOfBirth,phoneNumber,email,hospitalNumber,stateOfResidence,lgaOfResidence,address',
        'facility:facilityId,facilityName,facilityState',
        'bookedFacility:facilityId,facilityName,facilityState',
    ];

    /**
     * GET /screening-samples
     * ?status=collected|result_released|all  &cancerType=  &testType=  &search=
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $status = $request->query('status', 'collected');
        $search = trim((string) $request->query('search', ''));
        $visible = $user->visibleFacilityIds();

        $query = ScreeningSample::with(self::WITH_CLIENT)
            ->when($visible !== null, fn ($q) => $q->whereIn('facilityId', $visible))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->query('cancerType'), fn ($q, $v) => $q->where('cancerType', $v))
            ->when($request->query('testType'), fn ($q, $v) => $q->where('testType', $v))
            ->when($request->query('clientId'), fn ($q, $v) => $q->where('clientId', $v))
            ->when($request->query('visitId'), fn ($q, $v) => $q->where('visitId', $v))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('clientId', 'like', "%{$search}%")
                        ->orWhere('labSampleNumber', 'like', "%{$search}%")
                        ->orWhereHas('client', function ($c) use ($search) {
                            $c->where('fullName', 'like', "%{$search}%")
                              ->orWhere('phoneNumber', 'like', "%{$search}%");
                        });
                });
            });

        $query = $status === 'collected'
            ? $query->orderBy('sampleDate')
            : $query->orderByDesc('resultDate')->orderByDesc('sampleId');

        return response()->json(['samples' => $query->limit(200)->get()]);
    }

    /**
     * GET /screening-samples/results
     * All released results with filters, paging and totals.
     * ?cancerType= &testType= &severity=normal|suspicious|neutral &duplicates=1 &dateFrom= &dateTo= &clientId= &search= &page= &perPage=
     *
     * Results are grouped as non-suspicious (normal), suspicious (urgent; older "abnormal" rows count here
     * too) and inconclusive (neutral). A duplicate is a released result with the same client, test and
     * sample date as another one; each row carries duplicateCount (how many share that key).
     */
    public function results(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $visible = $user->visibleFacilityIds();
        $search = trim((string) $request->query('search', ''));

        $base = ScreeningSample::query()
            ->where('status', 'result_released')
            ->when($visible !== null, fn ($q) => $q->whereIn('facilityId', $visible))
            ->when($request->query('cancerType'), fn ($q, $v) => $q->where('cancerType', $v))
            ->when($request->query('testType'), fn ($q, $v) => $q->where('testType', $v))
            ->when($request->query('clientId'), fn ($q, $v) => $q->where('clientId', $v))
            ->when($request->query('dateFrom'), fn ($q, $v) => $q->whereDate('resultDate', '>=', $v))
            ->when($request->query('dateTo'), fn ($q, $v) => $q->whereDate('resultDate', '<=', $v))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('clientId', 'like', "%{$search}%")
                        ->orWhere('labSampleNumber', 'like', "%{$search}%")
                        ->orWhereHas('client', function ($c) use ($search) {
                            $c->where('fullName', 'like', "%{$search}%")
                              ->orWhere('phoneNumber', 'like', "%{$search}%");
                        });
                });
            });

        // Duplicate groups (same client + test + sample date) within the current filters.
        $dupGroups = (clone $base)
            ->selectRaw('clientId, testType, DATE(sampleDate) as d, COUNT(*) as n')
            ->groupBy('clientId', 'testType', DB::raw('DATE(sampleDate)'))
            ->havingRaw('COUNT(*) > 1')
            ->get();
        $dupCount = [];
        foreach ($dupGroups as $g) {
            $dupCount["{$g->clientId}|{$g->testType}|{$g->d}"] = (int) $g->n;
        }
        $onlyDuplicates = function ($q) use ($dupGroups) {
            if ($dupGroups->isEmpty()) {
                $q->whereRaw('1 = 0');
                return;
            }
            $q->where(function ($w) use ($dupGroups) {
                foreach ($dupGroups as $g) {
                    $w->orWhere(fn ($x) => $x->where('clientId', $g->clientId)->where('testType', $g->testType)->whereDate('sampleDate', $g->d));
                }
            });
        };

        // Totals ignore the outcome / duplicate filters so the summary tiles stay clickable filters.
        $summary = (clone $base)
            ->selectRaw('resultSeverity, COUNT(*) as n')
            ->groupBy('resultSeverity')
            ->pluck('n', 'resultSeverity');

        $severity = $request->query('severity');
        $rows = (clone $base)
            ->when($severity === 'suspicious' || $severity === 'urgent' || $severity === 'abnormal', fn ($q) => $q->whereIn('resultSeverity', ['urgent', 'abnormal']))
            ->when($severity === 'normal' || $severity === 'neutral', fn ($q) => $q->where('resultSeverity', $severity))
            ->when($request->boolean('duplicates'), $onlyDuplicates)
            ->with(self::WITH_CLIENT)
            // Duplicates are listed next to each other so they can be compared before deleting one.
            ->when($request->boolean('duplicates'), fn ($q) => $q->orderBy('clientId')->orderBy('testType')->orderByDesc('sampleDate'))
            ->orderByDesc('resultDate')
            ->orderBy('clientId')
            ->orderByDesc('sampleId')
            ->paginate(min((int) $request->query('perPage', 20), 500));

        $items = collect($rows->items())->map(function ($s) use ($dupCount) {
            $key = "{$s->clientId}|{$s->testType}|" . ($s->sampleDate ? $s->sampleDate->toDateString() : '');
            $s->setAttribute('duplicateCount', $dupCount[$key] ?? 1);
            return $s;
        });

        return response()->json([
            'results' => $items->values(),
            'total'   => $rows->total(),
            'page'    => $rows->currentPage(),
            'perPage' => $rows->perPage(),
            'summary' => [
                'total'      => (int) $summary->sum(),
                'normal'     => (int) ($summary['normal'] ?? 0),
                'suspicious' => (int) ($summary['urgent'] ?? 0) + (int) ($summary['abnormal'] ?? 0),
                'neutral'    => (int) ($summary['neutral'] ?? 0),
                'duplicates' => (int) collect($dupCount)->sum(),
                'duplicateGroups' => count($dupCount),
            ],
        ]);
    }

    /** POST /clients/{clientId}/screening-samples — record that a sample was taken / test performed. */
    public function store(Request $request, string $clientId): JsonResponse
    {
        $user = auth('api')->user();
        $client = Client::where('clientId', $clientId)->firstOrFail();

        if (!$user->canAccessFacility($client->facilityId)) {
            abort(403, 'You cannot access this client');
        }

        $request->validate(['testType' => ['required', Rule::in(ScreeningTestCatalog::keys())]]);
        $test = ScreeningTestCatalog::find($request->input('testType'));

        $sex = $test['sex'] ?? 'any';
        if ($sex !== 'any' && strtolower((string) $client->gender) !== $sex) {
            return response()->json([
                'message' => "{$test['label']} is only recorded for {$sex} clients.",
            ], 422);
        }

        $data = $request->validate([
            'sampleDate'       => ['required', 'date', 'before_or_equal:today'],
            'specimenType'     => [Rule::requiredIf(!empty($test['specimenTypes'])), 'nullable', Rule::in($test['specimenTypes'] ?? [])],
            'collectionMethod' => [Rule::requiredIf(!empty($test['collectionMethods'])), 'nullable',
                                   Rule::in(array_column($test['collectionMethods'] ?? [], 'value'))],
            'labSampleNumber'  => ['nullable', 'string', 'max:100'],
            'collectedBy'      => ['nullable', 'string', 'max:255'],
            'notes'            => ['nullable', 'string', 'max:1000'],
            'visitId'          => ['nullable', 'integer'],
        ]);

        $fields = [
            ...$data,
            'clientId'      => $client->clientId,
            'facilityId'    => $user->facilityId,
            'cancerType'    => $test['cancerType'],
            'testType'      => $test['key'],
            'collectedById' => $user->id,
            'status'        => 'collected',
        ];

        // A booking for the same test is turned into the real record instead of creating a duplicate.
        $booked = ScreeningSample::where('clientId', $client->clientId)
            ->where('testType', $test['key'])->where('status', 'booked')
            ->orderBy('sampleDate')->first();

        if ($booked) {
            $booked->update($fields);
            $sample = $booked->fresh();
        } else {
            $sample = ScreeningSample::create($fields);
        }

        $what = $test['kind'] === 'procedure' ? 'Test' : 'Sample';

        ScreeningLabSync::mirrorToCervical($sample);

        return response()->json([
            'message' => "{$what} recorded.",
            'sample'  => $sample->load(self::WITH_CLIENT),
        ], 201);
    }

    /**
     * POST /clients/{clientId}/screening-samples/book — schedule a test for a later date.
     * The booking is a screening_samples row with status "booked"; recording the test later (store above)
     * converts it, and it is ignored by the HPV mirror until then.
     */
    public function book(Request $request, string $clientId): JsonResponse
    {
        $user = auth('api')->user();
        $client = Client::where('clientId', $clientId)->firstOrFail();

        if (!$user->canAccessFacility($client->facilityId)) {
            abort(403, 'You cannot access this client');
        }

        $request->validate(['testType' => ['required', Rule::in(ScreeningTestCatalog::keys())]]);
        $test = ScreeningTestCatalog::find($request->input('testType'));

        $sex = $test['sex'] ?? 'any';
        if ($sex !== 'any' && strtolower((string) $client->gender) !== $sex) {
            return response()->json(['message' => "{$test['label']} is only booked for {$sex} clients."], 422);
        }

        $data = $request->validate([
            'bookedFor' => ['required', 'date', 'after_or_equal:today'],
            'facilityId' => ['required', 'integer', 'exists:facilities,facilityId'],
            'notes'     => ['nullable', 'string', 'max:1000'],
            'visitId'   => ['nullable', 'integer'],
        ]);

        if (ScreeningSample::where('clientId', $client->clientId)->where('testType', $test['key'])->where('status', 'booked')->exists()) {
            return response()->json(['message' => "{$test['label']} is already booked for this client."], 422);
        }

        $sample = ScreeningSample::create([
            'clientId'      => $client->clientId,
            'visitId'       => $data['visitId'] ?? null,
            'facilityId'    => $user->facilityId,
            'cancerType'    => $test['cancerType'],
            'testType'      => $test['key'],
            'sampleDate'    => $data['bookedFor'],
            'notes'         => $data['notes'] ?? null,
            'collectedById' => $user->id,
            'status'        => 'booked',
            'bookedFacilityId' => $data['facilityId'],
        ]);

        return response()->json(['message' => 'Test booked.', 'sample' => $sample->load(self::WITH_CLIENT)], 201);
    }

    /**
     * POST /screening-samples/{id}/attachment  (multipart: file)
     * Optional photo / PDF of the machine printout. Replaces any existing file.
     * Stored on the private disk and only served through attachment() below.
     */
    public function uploadAttachment(Request $request, int $id): JsonResponse
    {
        $user = auth('api')->user();
        $sample = ScreeningSample::findOrFail($id);

        if (!$user->canAccessFacility($sample->facilityId)) {
            abort(403, 'You cannot access this sample');
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ], [
            'file.mimes' => 'Upload a photo (JPG, PNG, WebP) or a PDF.',
            'file.max'   => 'The file is too large. The limit is 8 MB.',
        ]);

        $file = $request->file('file');
        $path = $file->storeAs(
            "screening-attachments/{$sample->sampleId}",
            bin2hex(random_bytes(8)) . '.' . strtolower($file->getClientOriginalExtension() ?: $file->extension()),
            'local'
        );

        if ($sample->resultAttachmentPath) {
            Storage::disk('local')->delete($sample->resultAttachmentPath);
        }

        $sample->update([
            'resultAttachmentPath' => $path,
            'resultAttachmentName' => mb_substr($file->getClientOriginalName(), 0, 200),
            'resultAttachmentMime' => $file->getMimeType(),
        ]);

        return response()->json(['message' => 'Image attached.', 'sample' => $sample->fresh()->load(self::WITH_CLIENT)]);
    }

    /** GET /screening-samples/{id}/attachment — streams the file to users who can see this sample. */
    public function attachment(int $id)
    {
        $user = auth('api')->user();
        $sample = ScreeningSample::findOrFail($id);

        if (!$user->canAccessFacility($sample->facilityId)) {
            abort(403, 'You cannot access this sample');
        }
        abort_if(!$sample->resultAttachmentPath || !Storage::disk('local')->exists($sample->resultAttachmentPath), 404, 'No image attached.');

        return Storage::disk('local')->response($sample->resultAttachmentPath, $sample->resultAttachmentName, [
            'Content-Type'           => $sample->resultAttachmentMime ?: 'application/octet-stream',
            'Cache-Control'          => 'private, max-age=0, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** DELETE /screening-samples/{id}/attachment */
    public function deleteAttachment(int $id): JsonResponse
    {
        $user = auth('api')->user();
        $sample = ScreeningSample::findOrFail($id);

        if (!$user->canAccessFacility($sample->facilityId)) {
            abort(403, 'You cannot access this sample');
        }

        if ($sample->resultAttachmentPath) {
            Storage::disk('local')->delete($sample->resultAttachmentPath);
        }
        $sample->update(['resultAttachmentPath' => null, 'resultAttachmentName' => null, 'resultAttachmentMime' => null]);

        return response()->json(['message' => 'Image removed.']);
    }

    /** PATCH /screening-samples/{id}/result — enter (or correct) the result. */
    public function recordResult(Request $request, int $id): JsonResponse
    {
        $user = auth('api')->user();
        $sample = ScreeningSample::findOrFail($id);

        if (!$user->canAccessFacility($sample->facilityId)) {
            abort(403, 'You cannot access this sample');
        }

        abort_if($sample->status === 'booked', 422, 'This test is only booked. Record the sample or test first.');

        $test = ScreeningTestCatalog::find($sample->testType);
        abort_if(!$test, 422, 'This test type is no longer configured.');

        $hasValue = !empty($test['value']);
        $detail = $test['detail'] ?? null;
        $resultValues = array_column($test['results'], 'value');
        $extraFields = $test['extraFields'] ?? [];

        // A run flagged invalid / error on the machine report can't give a positive or negative answer.
        $inconclusive = false;
        foreach ($extraFields as $f) {
            if (!empty($f['inconclusiveWhen']) && in_array($request->input("resultMeta.{$f['name']}"), $f['inconclusiveWhen'], true)) {
                $inconclusive = true;
            }
        }

        $rules = [
            'resultDate'  => ['required', 'date', 'before_or_equal:today', 'after_or_equal:' . $sample->sampleDate->toDateString()],
            'resultNotes' => ['nullable', 'string', 'max:1000'],
            'result'      => [($hasValue || $inconclusive) ? 'nullable' : 'required', Rule::in($resultValues)],
            'resultMeta'  => ['nullable', 'array'],
        ];
        foreach ($extraFields as $f) {
            // A field shown only for some answers (showWhen) is required only when it is shown.
            $shown = empty($f['showWhen'])
                || in_array((string) $request->input("resultMeta.{$f['showWhen']['field']}"), $f['showWhen']['in'], true);
            $r = [!empty($f['required']) && $shown ? 'required' : 'nullable'];
            if ($f['type'] === 'select') {
                // Options are either plain values or { value, label } pairs.
                $r[] = Rule::in(array_map(fn ($o) => is_array($o) ? $o['value'] : $o, $f['options']));
            } elseif ($f['type'] === 'number') {
                $r[] = 'numeric';
                if (isset($f['min'])) $r[] = 'min:' . $f['min'];
                if (isset($f['max'])) $r[] = 'max:' . $f['max'];
            } else {
                $r[] = 'string';
                $r[] = 'max:100';
            }
            $rules["resultMeta.{$f['name']}"] = $r;
        }
        if ($sample->testType === 'hpv_dna') {
            $rules['resultMeta.hrCategory'] = ['nullable', Rule::in(['16_18', '31_33_35_45_52_58', 'other'])];
            $rules['resultMeta.ct'] = ['nullable', 'numeric', 'min:0', 'max:60'];
            $rules['resultMeta.riskCategory']  = ['nullable', Rule::in(['very_low', 'low', 'medium', 'high'])];
            $rules['resultMeta.riskSuggested'] = ['nullable', Rule::in(['low', 'medium', 'high'])];
            $rules['resultMeta.riskOverridden'] = ['nullable', Rule::in(['yes'])];
        }
        if ($hasValue) {
            $rules['resultValue'] = ['required', 'numeric', 'min:' . $test['value']['min'], 'max:' . $test['value']['max']];
        }
        if ($detail) {
            $rules[$detail['name']] = [
                Rule::requiredIf(!$inconclusive && in_array($request->input('result'), $detail['requiredFor'], true)),
                'nullable',
                Rule::in(array_column($detail['options'], 'value')),
            ];
        }

        $data = $request->validate($rules, [
            'resultDate.after_or_equal' => 'The result date cannot be earlier than the date the sample was taken.',
        ]);

        // For numeric tests the server decides the category, so a wrong client-side label can't be saved.
        if ($inconclusive) {
            $result = collect($test['results'])->firstWhere('severity', 'neutral')['value'] ?? ($data['result'] ?? null);
        } else {
            $result = $hasValue
                ? ScreeningTestCatalog::deriveResult($test, (float) $data['resultValue'])
                : $data['result'];
        }

        $severity = collect($test['results'])->firstWhere('value', $result)['severity'] ?? 'neutral';

        $detailValue = null;
        if ($detail && in_array($result, $detail['requiredFor'], true)) {
            $detailValue = $data[$detail['name']] ?? null;
        }

        $sample->update([
            'result'            => $result,
            'resultSeverity'    => $severity,
            'resultValue'       => $hasValue ? $data['resultValue'] : null,
            'resultDetail'      => $detailValue,
            'resultMeta'        => collect($data['resultMeta'] ?? [])->filter(fn ($v) => $v !== null && $v !== '')->all() ?: null,
            'resultDate'        => $data['resultDate'],
            'resultNotes'       => $data['resultNotes'] ?? null,
            'status'            => 'result_released',
            'resultEnteredById' => $user->id,
        ]);

        ScreeningLabSync::mirrorToCervical($sample->fresh());

        return response()->json([
            'message' => 'Result recorded.',
            'sample'  => $sample->fresh()->load(self::WITH_CLIENT),
        ]);
    }

    /**
     * POST /visits/{visitId}/lab-sync
     * Called by the clinical screening page after it saves the cervical findings, so the HPV sample
     * date / result typed there also appear on the Samples & Test Results pages.
     */
    public function syncFromVisit(int $visitId): JsonResponse
    {
        $user = auth('api')->user();
        $visit = ScreeningVisit::where('visitId', $visitId)->firstOrFail();
        $client = Client::where('clientId', $visit->clientId)->firstOrFail();

        if (!$user->canAccessFacility($client->facilityId)) {
            abort(403, 'You cannot access this client');
        }

        $sample = ScreeningLabSync::fromVisit($visit, $user);

        return response()->json(['synced' => (bool) $sample, 'sample' => $sample?->load(self::WITH_CLIENT)]);
    }
}
