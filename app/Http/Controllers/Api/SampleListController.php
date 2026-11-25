<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScreeningSample;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /screening-samples/list — samples / tests with filters (NCSR Mobile App).
 *   ?status=all|collected|booked|result_released
 *   &facilityId=  &cancerType=  &testType=  &clientId=
 *   &dateFrom=&dateTo=      sample date (result date for released results)
 *   &overdueDays=7          only samples still waiting more than N days
 *   &search=                client ID, name, phone, lab number
 * Same scope as GET /screening-samples (national users see every facility). Returns { samples } (max 300).
 */
class SampleListController extends Controller
{
    private const WITH = [
        'client:clientId,fullName,gender,dateOfBirth,phoneNumber,hospitalNumber',
        'facility:facilityId,facilityName,facilityState',
    ];

    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $visible = $user->visibleFacilityIds();
        $status = (string) $request->query('status', 'all');
        $search = trim((string) $request->query('search', ''));
        $facilityId = $request->filled('facilityId') && $request->facilityId !== 'all' ? (int) $request->facilityId : null;
        if ($facilityId && $visible !== null && !in_array($facilityId, array_map('intval', $visible), true)) {
            abort(403, 'You cannot see samples of this facility.');
        }
        $dateCol = $status === 'result_released' ? 'resultDate' : 'sampleDate';

        $q = ScreeningSample::with(self::WITH)
            ->when($visible !== null, fn ($x) => $x->whereIn('facilityId', $visible))
            ->when($facilityId, fn ($x) => $x->where('facilityId', $facilityId))
            ->when($status !== 'all', fn ($x) => $x->where('status', $status))
            ->when($request->query('cancerType'), fn ($x, $v) => $x->where('cancerType', $v))
            ->when($request->query('testType'), fn ($x, $v) => $x->where('testType', $v))
            ->when($request->query('clientId'), fn ($x, $v) => $x->where('clientId', $v))
            ->when($request->query('dateFrom'), fn ($x, $v) => $x->whereDate($dateCol, '>=', $v))
            ->when($request->query('dateTo'), fn ($x, $v) => $x->whereDate($dateCol, '<=', $v))
            ->when((int) $request->query('overdueDays') > 0, fn ($x) => $x->where('status', 'collected')
                ->whereDate('sampleDate', '<=', now()->subDays((int) $request->query('overdueDays'))->toDateString()))
            ->when($search !== '', function ($x) use ($search) {
                $x->where(function ($s) use ($search) {
                    $s->where('clientId', 'like', "%{$search}%")
                      ->orWhere('labSampleNumber', 'like', "%{$search}%")
                      ->orWhereHas('client', fn ($c) => $c->where('fullName', 'like', "%{$search}%")->orWhere('phoneNumber', 'like', "%{$search}%"));
                });
            });

        $q = $status === 'collected' ? $q->orderBy('sampleDate') : $q->orderByDesc($dateCol)->orderByDesc('sampleId');

        return response()->json(['samples' => $q->limit(300)->get()]);
    }
}
