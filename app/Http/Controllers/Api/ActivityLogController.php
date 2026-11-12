<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public const ACTIONS = [
        'client_id_changed'          => 'Client ID changed',
        'sample_deleted'             => 'Sample / result deleted',
        'sample_deletion_requested'  => 'Deletion requested',
        'sample_deletion_approved'   => 'Deletion approved',
        'sample_deletion_rejected'   => 'Deletion rejected',
        'sample_restored'            => 'Sample / result restored',
        'overdue_results_reminder'   => 'Overdue-result reminder emailed',
    ];

    private function canView($user): bool
    {
        return $user->isSuperAdmin()
            || $user->isNicratStaff()
            || (method_exists($user, 'isHospitalAdminRole') && $user->isHospitalAdminRole());
    }

    /**
     * GET /activity-logs?action=&search=&dateFrom=&dateTo=&page=&perPage=
     * Admins only. Users tied to facilities only see logs for the facilities they can see.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        abort_if(!$this->canView($user), 403, 'Only an admin can view the activity log.');

        $visible = $user->visibleFacilityIds();
        $search = trim((string) $request->query('search', ''));

        $rows = ActivityLog::query()
            ->when($visible !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('facilityId', $visible)->orWhere('userId', $user->id)))
            ->when($request->query('action'), fn ($q, $v) => $q->where('action', $v))
            ->when($request->query('dateFrom'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->query('dateTo'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('description', 'like', "%{$search}%")
                      ->orWhere('clientId', 'like', "%{$search}%")
                      ->orWhere('subjectId', 'like', "%{$search}%")
                      ->orWhere('userName', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('logId')
            ->paginate(min((int) $request->query('perPage', 25), 200));

        // Fill in names for rows written without one (e.g. back-filled history).
        $missing = collect($rows->items())->whereNull('userName')->pluck('userId')->filter()->unique();
        $names = $missing->isEmpty() ? collect() : User::whereIn((new User())->getKeyName(), $missing)->get()
            ->mapWithKeys(fn ($u) => [$u->getKey() => trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')) ?: ($u->email ?? '')]);

        return response()->json([
            'logs' => collect($rows->items())->map(fn ($l) => [
                'logId'       => $l->logId,
                'action'      => $l->action,
                'actionLabel' => self::ACTIONS[$l->action] ?? ucfirst(str_replace('_', ' ', $l->action)),
                'subjectType' => $l->subjectType,
                'subjectId'   => $l->subjectId,
                'clientId'    => $l->clientId,
                'description' => $l->description,
                'details'     => $l->details,
                'userName'    => $l->userName ?? ($names[$l->userId] ?? null),
                'ipAddress'   => $l->ipAddress,
                'createdAt'   => $l->created_at,
            ])->values(),
            'total'   => $rows->total(),
            'page'    => $rows->currentPage(),
            'perPage' => $rows->perPage(),
            'actions' => collect(self::ACTIONS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }
}
