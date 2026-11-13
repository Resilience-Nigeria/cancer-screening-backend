<?php

namespace App\Http\Controllers\Api\Bloom;

use App\Http\Controllers\Controller;
use App\Models\BloomCase;
use App\Models\BloomCaseTest;
use App\Models\ScreeningSample;
use App\Services\Bloom\BloomNcsrClientService;
use App\Support\ScreeningLabSync;
use App\Support\ScreeningTestCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Breast screening recorded by a health worker in the Bloom app. Samples and results are written to the
 * NCSR registry (screening_samples, with the same rules as ScreeningSampleController) and copied to the
 * Bloom registry (bloom_case_tests). The woman is registered as an NCSR client the first time.
 */
class NavigatorScreeningController extends Controller
{
    use CaseConcerns;

    private const CANCER = 'breast';

    /** GET /bloom-chw/screening-tests — the breast tests from the NCSR catalogue (fields, options, results). */
    public function tests(): JsonResponse
    {
        if (!$this->isActiveNavigator($this->staff())) {
            return $this->deny();
        }

        return response()->json(['status' => true, 'data' => $this->breastTests()]);
    }

    /** GET /bloom-chw/cases/{id}/screening — her NCSR client (if any) and every breast sample/result. */
    public function show(int $id): JsonResponse
    {
        [$case, $error] = $this->loadCase($id);
        if ($error) {
            return $error;
        }

        $client = BloomNcsrClientService::find($case);
        if ($client && $case->ncsr_client_id !== $client->clientId) {
            $case->update(['ncsr_client_id' => $client->clientId]);
        }

        $samples = $client
            ? ScreeningSample::where('clientId', $client->clientId)->where('cancerType', self::CANCER)
                ->orderByDesc('sampleDate')->orderByDesc('sampleId')->limit(100)->get()
            : collect();

        // Keep the Bloom copy of this case's records up to date (including ones deleted in NCSR).
        foreach ($samples as $s) {
            if (BloomCaseTest::where('sample_id', $s->sampleId)->where('case_id', $case->id)->exists()) {
                $this->snapshot($case, $s);
            }
        }
        $live = $samples->pluck('sampleId')->all();
        BloomCaseTest::where('case_id', $case->id)->where('status', '!=', 'deleted')
            ->when(!empty($live), fn ($q) => $q->whereNotIn('sample_id', $live))
            ->get()
            ->each(function ($t) {
                if (ScreeningSample::onlyTrashed()->where('sampleId', $t->sample_id)->exists()) {
                    $t->update(['status' => 'deleted']);
                }
            });

        $f = $case->facility;

        return response()->json([
            'status' => true,
            'data'   => [
                'client'   => $client ? $this->presentClient($client) : null,
                // Used to pre-fill the registration form.
                'defaults' => [
                    'stateOfResidence' => $f->facilityState ?? null,
                    'lgaOfResidence'   => $f->facilityLga ?? null,
                    'age'              => $case->account->age ?? null,
                ],
                'samples'  => $samples->map(fn ($s) => $this->presentSample($s))->values(),
            ],
        ]);
    }

    /** POST /bloom-chw/cases/{id}/ncsr-client — find or register her in the NCSR registry. */
    public function registerClient(Request $request, int $id): JsonResponse
    {
        [$case, $error] = $this->loadCase($id);
        if ($error) {
            return $error;
        }

        $data = $request->validate([
            'dateOfBirth'      => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'age'              => ['nullable', 'integer', 'min:10', 'max:120'],
            'stateOfResidence' => ['nullable', 'string', 'max:255'],
            'lgaOfResidence'   => ['nullable', 'string', 'max:255'],
            'address'          => ['nullable', 'string', 'max:300'],
        ]);
        if (empty($data['dateOfBirth']) && empty($data['age']) && empty($case->account->age) && !BloomNcsrClientService::find($case)) {
            return response()->json(['status' => false, 'code' => 'AGE_REQUIRED', 'message' => "Enter her date of birth or age."], 422);
        }

        $client = BloomNcsrClientService::ensure($case, $this->staff(), $data);

        return response()->json(['status' => true, 'message' => 'Registered in NCSR.', 'data' => ['client' => $this->presentClient($client)]]);
    }

    /** POST /bloom-chw/cases/{id}/samples — sample taken / test performed (same rules as the web app). */
    public function storeSample(Request $request, int $id): JsonResponse
    {
        [$case, $error] = $this->loadCase($id);
        if ($error) {
            return $error;
        }
        $user = $this->staff();

        $client = BloomNcsrClientService::find($case);
        if (!$client) {
            return response()->json(['status' => false, 'code' => 'NEEDS_CLIENT', 'message' => 'Register her in NCSR first.'], 422);
        }

        $keys = array_column($this->breastTests(), 'key');
        $request->validate(['testType' => ['required', Rule::in($keys)]]);
        $test = ScreeningTestCatalog::find($request->input('testType'));

        $data = $request->validate([
            'sampleDate'       => ['required', 'date', 'before_or_equal:today'],
            'specimenType'     => [Rule::requiredIf(!empty($test['specimenTypes'])), 'nullable', Rule::in($test['specimenTypes'] ?? [])],
            'collectionMethod' => [Rule::requiredIf(!empty($test['collectionMethods'])), 'nullable', Rule::in(array_column($test['collectionMethods'] ?? [], 'value'))],
            'labSampleNumber'  => ['nullable', 'string', 'max:100'],
            'collectedBy'      => ['nullable', 'string', 'max:255'],
            'notes'            => ['nullable', 'string', 'max:1000'],
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

        // As on the web: a booking for the same test becomes the real record.
        $booked = ScreeningSample::where('clientId', $client->clientId)->where('testType', $test['key'])->where('status', 'booked')->orderBy('sampleDate')->first();
        if ($booked) {
            $booked->update($fields);
            $sample = $booked->fresh();
        } else {
            $sample = ScreeningSample::create($fields);
        }
        if (class_exists(ScreeningLabSync::class)) {
            ScreeningLabSync::mirrorToCervical($sample);
        }

        $this->snapshot($case, $sample);
        $this->progress($case);

        $what = $test['kind'] === 'procedure' ? 'Test' : 'Sample';

        return response()->json(['status' => true, 'message' => "{$what} recorded.", 'data' => $this->presentSample($sample->fresh())], 201);
    }

    /** PATCH /bloom-chw/samples/{sampleId}/result — same validation as ScreeningSampleController::recordResult. */
    public function recordResult(Request $request, int $sampleId): JsonResponse
    {
        $user = $this->staff();
        if (!$this->isActiveNavigator($user)) {
            return $this->deny();
        }

        $sample = ScreeningSample::find($sampleId);
        $link   = BloomCaseTest::where('sample_id', $sampleId)->first();
        $case   = $link ? BloomCase::find($link->case_id) : null;
        if (!$sample || !$case || !$this->visibleTo($case, $user)) {
            return response()->json(['status' => false, 'code' => 'NOT_FOUND', 'message' => 'Sample not found.'], 404);
        }
        abort_if($sample->status === 'booked', 422, 'This test is only booked. Record the sample or test first.');

        $test = ScreeningTestCatalog::find($sample->testType);
        abort_if(!$test, 422, 'This test type is no longer configured.');

        $hasValue = !empty($test['value']);
        $detail = $test['detail'] ?? null;
        $resultValues = array_column($test['results'], 'value');
        $extraFields = $test['extraFields'] ?? [];

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
            $shown = empty($f['showWhen'])
                || in_array((string) $request->input("resultMeta.{$f['showWhen']['field']}"), $f['showWhen']['in'], true);
            $r = [!empty($f['required']) && $shown ? 'required' : 'nullable'];
            if ($f['type'] === 'select') {
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

        if ($inconclusive) {
            $result = collect($test['results'])->firstWhere('severity', 'neutral')['value'] ?? ($data['result'] ?? null);
        } else {
            $result = $hasValue ? ScreeningTestCatalog::deriveResult($test, (float) $data['resultValue']) : $data['result'];
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
        if (class_exists(ScreeningLabSync::class)) {
            ScreeningLabSync::mirrorToCervical($sample->fresh());
        }

        $this->snapshot($case, $sample->fresh());

        return response()->json(['status' => true, 'message' => 'Result recorded.', 'data' => $this->presentSample($sample->fresh())]);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    private function breastTests(): array
    {
        return collect(ScreeningTestCatalog::keys())
            ->map(fn ($k) => ScreeningTestCatalog::find($k))
            ->filter(fn ($t) => $t && ($t['cancerType'] ?? null) === self::CANCER && in_array($t['sex'] ?? 'any', ['female', 'any'], true))
            ->values()->all();
    }

    private function visibleTo(BloomCase $case, $user): bool
    {
        return (int) $case->navigator_id === (int) $user->id
            || (!$case->navigator_id && (int) $case->facility_id === (int) $user->facilityId);
    }

    /** @return array{0: ?BloomCase, 1: ?JsonResponse} */
    private function loadCase(int $id): array
    {
        $user = $this->staff();
        if (!$this->isActiveNavigator($user)) {
            return [null, $this->deny('Only verified community health workers can record screening.')];
        }
        $case = BloomCase::with(['account', 'facility'])->find($id);
        if (!$case || !$this->visibleTo($case, $user)) {
            return [null, response()->json(['status' => false, 'code' => 'NOT_FOUND', 'message' => 'Case not found.'], 404)];
        }
        if (!$case->navigator_id) {
            $case->update(['navigator_id' => $user->id, 'assigned_at' => now()]);
        }

        return [$case, null];
    }

    /** A sample at the PHC means the community follow-up is done and screening is under way. */
    private function progress(BloomCase $case): void
    {
        if ($case->step_community !== 'completed') {
            $case->step_community = 'completed';
        }
        if ($case->step_phc === 'not-started') {
            $case->step_phc = 'in-progress';
        }
        if ($case->isDirty()) {
            $case->save();
        }
    }

    private function snapshot(BloomCase $case, ScreeningSample $s): void
    {
        BloomCaseTest::updateOrCreate(['sample_id' => $s->sampleId], [
            'case_id'           => $case->id,
            'ncsr_client_id'    => $s->clientId,
            'cancer_type'       => $s->cancerType,
            'test_type'         => $s->testType,
            'status'            => $s->status,
            'sample_ref'        => $s->sampleRef,
            'sample_date'       => optional($s->sampleDate)->toDateString(),
            'specimen_type'     => $s->specimenType,
            'collection_method' => $s->collectionMethod,
            'result'            => $s->result,
            'result_severity'   => $s->resultSeverity,
            'result_detail'     => $s->resultDetail,
            'result_value'      => $s->resultValue,
            'result_meta'       => is_array($s->resultMeta) ? $s->resultMeta : (json_decode((string) $s->resultMeta, true) ?: null),
            'result_date'       => $s->resultDate ? substr((string) $s->resultDate, 0, 10) : null,
            'result_notes'      => $s->resultNotes,
            'recorded_by'       => $this->staff()?->id,
        ]);
    }

    private function presentClient($c): array
    {
        return [
            'clientId'         => $c->clientId,
            'fullName'         => $c->fullName,
            'age'              => $c->age,
            'phoneNumber'      => $c->phoneNumber,
            'stateOfResidence' => $c->stateOfResidence,
            'lgaOfResidence'   => $c->lgaOfResidence,
        ];
    }

    private function presentSample(ScreeningSample $s): array
    {
        $meta = is_array($s->resultMeta) ? $s->resultMeta : (json_decode((string) $s->resultMeta, true) ?: null);

        return [
            'sampleId'         => $s->sampleId,
            'sampleRef'        => $s->sampleRef,
            'testType'         => $s->testType,
            'status'           => $s->status,
            'sampleDate'       => $s->sampleDate ? substr((string) $s->sampleDate, 0, 10) : null,
            'specimenType'     => $s->specimenType,
            'collectionMethod' => $s->collectionMethod,
            'labSampleNumber'  => $s->labSampleNumber,
            'collectedBy'      => $s->collectedBy,
            'notes'            => $s->notes,
            'result'           => $s->result,
            'resultSeverity'   => $s->resultSeverity,
            'resultValue'      => $s->resultValue,
            'resultDetail'     => $s->resultDetail,
            'resultMeta'       => $meta,
            'resultDate'       => $s->resultDate ? substr((string) $s->resultDate, 0, 10) : null,
            'resultNotes'      => $s->resultNotes,
            'fromBloom'        => BloomCaseTest::where('sample_id', $s->sampleId)->exists(),
            // A health worker can't delete a result directly; their request waits for an admin (NCSR deletion requests).
            'deletionPending'  => class_exists(\App\Models\SampleDeletionRequest::class)
                && \App\Models\SampleDeletionRequest::where('sampleId', $s->sampleId)->where('status', 'pending')->exists(),
        ];
    }
}
