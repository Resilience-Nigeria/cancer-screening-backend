<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ScreeningSample;
use App\Support\ScreeningTestCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScreeningSampleController extends Controller
{
    private const WITH_CLIENT = 'client:clientId,fullName,gender,dateOfBirth,phoneNumber';

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
     * All released results with filters, paging and severity totals.
     * ?cancerType= &testType= &severity= &dateFrom= &dateTo= &clientId= &search= &page= &perPage=
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

        // Totals ignore the severity filter so the summary tiles stay clickable filters.
        $summary = (clone $base)
            ->selectRaw('resultSeverity, COUNT(*) as n')
            ->groupBy('resultSeverity')
            ->pluck('n', 'resultSeverity');

        $rows = (clone $base)
            ->when($request->query('severity'), fn ($q, $v) => $q->where('resultSeverity', $v))
            ->with(self::WITH_CLIENT)
            ->orderByDesc('resultDate')
            ->orderByDesc('sampleId')
            ->paginate(min((int) $request->query('perPage', 20), 500));

        return response()->json([
            'results' => $rows->items(),
            'total'   => $rows->total(),
            'page'    => $rows->currentPage(),
            'perPage' => $rows->perPage(),
            'summary' => [
                'total'    => (int) $summary->sum(),
                'normal'   => (int) ($summary['normal'] ?? 0),
                'abnormal' => (int) ($summary['abnormal'] ?? 0),
                'urgent'   => (int) ($summary['urgent'] ?? 0),
                'neutral'  => (int) ($summary['neutral'] ?? 0),
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

        $sample = ScreeningSample::create([
            ...$data,
            'clientId'      => $client->clientId,
            'facilityId'    => $user->facilityId,
            'cancerType'    => $test['cancerType'],
            'testType'      => $test['key'],
            'collectedById' => $user->id,
            'status'        => 'collected',
        ]);

        $what = $test['kind'] === 'procedure' ? 'Test' : 'Sample';

        return response()->json([
            'message' => "{$what} recorded.",
            'sample'  => $sample->load(self::WITH_CLIENT),
        ], 201);
    }

    /** PATCH /screening-samples/{id}/result — enter (or correct) the result. */
    public function recordResult(Request $request, int $id): JsonResponse
    {
        $user = auth('api')->user();
        $sample = ScreeningSample::findOrFail($id);

        if (!$user->canAccessFacility($sample->facilityId)) {
            abort(403, 'You cannot access this sample');
        }

        $test = ScreeningTestCatalog::find($sample->testType);
        abort_if(!$test, 422, 'This test type is no longer configured.');

        $hasValue = !empty($test['value']);
        $detail = $test['detail'] ?? null;
        $resultValues = array_column($test['results'], 'value');

        $rules = [
            'resultDate'  => ['required', 'date', 'before_or_equal:today', 'after_or_equal:' . $sample->sampleDate->toDateString()],
            'resultNotes' => ['nullable', 'string', 'max:1000'],
            'result'      => [$hasValue ? 'nullable' : 'required', Rule::in($resultValues)],
        ];
        if ($hasValue) {
            $rules['resultValue'] = ['required', 'numeric', 'min:' . $test['value']['min'], 'max:' . $test['value']['max']];
        }
        if ($detail) {
            $rules[$detail['name']] = [
                Rule::requiredIf(in_array($request->input('result'), $detail['requiredFor'], true)),
                'nullable',
                Rule::in(array_column($detail['options'], 'value')),
            ];
        }

        $data = $request->validate($rules, [
            'resultDate.after_or_equal' => 'The result date cannot be earlier than the date the sample was taken.',
        ]);

        // For numeric tests the server decides the category, so a wrong client-side label can't be saved.
        $result = $hasValue
            ? ScreeningTestCatalog::deriveResult($test, (float) $data['resultValue'])
            : $data['result'];

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
            'resultDate'        => $data['resultDate'],
            'resultNotes'       => $data['resultNotes'] ?? null,
            'status'            => 'result_released',
            'resultEnteredById' => $user->id,
        ]);

        return response()->json([
            'message' => 'Result recorded.',
            'sample'  => $sample->fresh()->load(self::WITH_CLIENT),
        ]);
    }
}
