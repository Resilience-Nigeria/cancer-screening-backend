<?php

namespace App\Http\Controllers\Api\Bloom;

use App\Http\Controllers\Controller;
use App\Models\BloomCase;
use App\Models\User;
use App\Services\Bloom\BloomCaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Admins see Bloom cases in their facilities and (re)assign them to health workers. */
class AdminCaseController extends Controller
{
    use CaseConcerns;

    private const STAGES = ['new', 'community', 'screening', 'referral', 'completed', 'withdrawn'];

    /** GET /bloom-admin/cases */
    public function index(Request $request): JsonResponse
    {
        $me = $this->staff();
        if (!$this->canReview($me)) {
            return $this->deny();
        }

        $v = $request->validate([
            'stage'       => ['nullable', 'string'],
            'facilityId'  => ['nullable', 'integer'],
            'navigatorId' => ['nullable', 'string'], // user id, or "none" for unassigned
            'search'      => ['nullable', 'string', 'max:50'],
            'page'        => ['nullable', 'integer', 'min:1'],
            'perPage'     => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        BloomCaseService::sync();

        $base = $this->scopeFacilities(BloomCase::query(), $me);
        if (!empty($v['facilityId'])) {
            $base->where('facility_id', (int) $v['facilityId']);
        }
        if (($v['navigatorId'] ?? '') === 'none') {
            $base->whereNull('navigator_id');
        } elseif (!empty($v['navigatorId'])) {
            $base->where('navigator_id', (int) $v['navigatorId']);
        }
        if (!empty($v['search'])) {
            $digits = (int) preg_replace('/\D+/', '', $v['search']);
            $base->where('id', $digits ?: 0);
        }

        $summary = ['unassigned' => (clone $base)->whereNull('navigator_id')->whereNull('closed_at')->count()];
        foreach (self::STAGES as $s) {
            $summary[$s] = $this->whereStage(clone $base, $s)->count();
        }

        $list = clone $base;
        if (!empty($v['stage']) && (in_array($v['stage'], self::STAGES, true) || $v['stage'] === 'open')) {
            $this->whereStage($list, $v['stage']);
        }

        $perPage = (int) ($v['perPage'] ?? 20);
        $page    = (int) ($v['page'] ?? 1);
        $total   = (clone $list)->count();
        $rows    = $list->with(['appointment', 'account', 'ncsrClient', 'facility', 'navigator'])
            ->orderByRaw('navigator_id IS NULL DESC')->orderByRaw('closed_at IS NULL DESC')->orderByDesc('id')
            ->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'status'  => true,
            'cases'   => $rows->map(fn ($c) => $this->presentCase($c, true))->values(),
            'total'   => $total,
            'summary' => $summary,
        ]);
    }

    /** GET /bloom-admin/cases/navigators?facilityId=  — active health workers to assign to, with their open load. */
    public function navigators(Request $request): JsonResponse
    {
        $me = $this->staff();
        if (!$this->canReview($me)) {
            return $this->deny();
        }
        $roleId = BloomCaseService::navigatorRoleId();
        $q = User::where('role', $roleId ?: 0)->where('status', 'active');
        $this->scopeFacilities($q, $me, 'facilityId');
        if ($request->filled('facilityId')) {
            $q->where('facilityId', (int) $request->query('facilityId'));
        }
        $users = $q->with('facility')->orderBy('firstName')->limit(300)->get();
        $load  = BloomCase::whereIn('navigator_id', $users->pluck('id'))->whereNull('closed_at')
            ->selectRaw('navigator_id, COUNT(*) as n')->groupBy('navigator_id')->pluck('n', 'navigator_id');

        return response()->json(['status' => true, 'data' => $users->map(fn ($u) => [
            'id'         => $u->id,
            'name'       => trim("{$u->firstName} {$u->lastName}"),
            'facilityId' => (int) $u->facilityId,
            'facility'   => $u->facility->facilityName ?? null,
            'openCases'  => (int) ($load[$u->id] ?? 0),
        ])->values()]);
    }

    /** POST /bloom-admin/cases/{id}/assign  { navigatorId: number|null } */
    public function assign(Request $request, int $id): JsonResponse
    {
        $me = $this->staff();
        if (!$this->canReview($me)) {
            return $this->deny();
        }
        $data = $request->validate(['navigatorId' => ['nullable', 'integer']]);

        $case = $this->scopeFacilities(BloomCase::query(), $me)->find($id);
        if (!$case) {
            return response()->json(['status' => false, 'code' => 'NOT_FOUND', 'message' => 'Case not found.'], 404);
        }

        if (!empty($data['navigatorId'])) {
            $nav = User::where('id', $data['navigatorId'])->where('role', BloomCaseService::navigatorRoleId() ?: 0)->where('status', 'active')->first();
            if (!$nav || !$me->canAccessFacility((int) $nav->facilityId)) {
                return response()->json(['status' => false, 'code' => 'INVALID_NAVIGATOR', 'message' => 'Choose an active health worker from your facilities.'], 422);
            }
            $case->update(['navigator_id' => $nav->id, 'assigned_by' => $me->id, 'assigned_at' => now()]);
            \App\Services\Bloom\BloomStaffNotifier::notify($nav->id, 'case_assigned', 'Case assigned to you: ' . $case->code(), 'An admin assigned this case to you.', $case->id);
        } else {
            $case->update(['navigator_id' => null, 'assigned_by' => $me->id, 'assigned_at' => null]);
        }

        $case->load(['appointment', 'account', 'ncsrClient', 'facility', 'navigator']);

        return response()->json(['status' => true, 'message' => 'Assignment saved.', 'case' => $this->presentCase($case, true)]);
    }
}
