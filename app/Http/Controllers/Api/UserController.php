<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Facility;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Models\UserFacilityGrant;
use App\Services\BrevoMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * User Management. What each person may do comes from their roles' settings on the Roles page
 * (canManageUsers, manageableRoleIds, data scope), not from role names in code:
 *  - can open the page: Super Admin, or any role with "Can manage users"
 *  - sees: everyone except Super Admins when their scope is country-wide; otherwise people in their facilities
 *    whose roles they are allowed to give
 *  - can add / edit / delete: only people whose roles are all ones they may give, in facilities they can see
 * A user can hold several roles (the first is the main role) and several facilities.
 */
class UserController extends Controller
{
    private function generatePassword(): string
    {
        return Str::upper(Str::random(2)) . Str::lower(Str::random(4)) . rand(100, 999) . Str::random(1);
    }

    private function sendWelcomeEmail(User $user, string $plainPassword): void
    {
        try {
            app(BrevoMailService::class)->sendWelcomeEmail($user, $plainPassword);
        } catch (\Exception $e) {
            \Log::error('Failed to send welcome email: ' . $e->getMessage());
        }
    }

    private function log(string $action, string $text, User $user, array $details = []): void
    {
        if (class_exists(ActivityLog::class)) {
            ActivityLog::record($action, $text, ['subjectType' => 'user', 'subjectId' => $user->id, 'details' => $details]);
        }
    }

    private function deny(string $message, int $code = 403): JsonResponse
    {
        return response()->json(['status' => false, 'message' => $message], $code);
    }

    private function nameOf(User $u): string
    {
        return trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')) ?: (string) $u->email;
    }

    /** Role ids of a user (main + extra). */
    private function roleIdsOf(User $u): array
    {
        return $u->allRoles()->pluck('roleId')->map(fn ($v) => (int) $v)->all();
    }

    /** May the signed-in manager change this user? */
    private function canChange(User $me, User $u): bool
    {
        if ($u->isSuperAdmin()) return false;
        if (array_diff($this->roleIdsOf($u), $me->manageableRoleIds())) return false;
        $scope = $me->visibleFacilityIds();
        if ($scope === null) return true;
        $theirs = array_filter(array_merge([$u->facilityId], $u->facilityGrants()->pluck('facilityId')->all()));
        return $theirs && !array_diff(array_map('intval', $theirs), $scope);
    }

    private function format(User $u, ?User $me = null): array
    {
        $roles = $u->allRoles();
        $grants = $u->relationLoaded('facilityGrants') ? $u->facilityGrants : $u->facilityGrants()->with('facility')->get();
        $assigned = $roles->contains(fn ($r) => $r->dataScopeType === 'assigned');

        $access = $u->hasNationalAccess()
            ? 'All facilities'
            : (($n = count($u->visibleFacilityIds() ?? [])) === 1 ? '1 facility' : "{$n} facilities");

        return [
            'id' => $u->id,
            'firstName' => $u->firstName,
            'lastName' => $u->lastName,
            'email' => $u->email,
            'phoneNumber' => $u->phoneNumber,
            'alternatePhoneNumber' => $u->alternatePhoneNumber,
            'role' => $u->user_role?->roleName ?? $u->role,          // main role name (older screens)
            'roleId' => $u->role,
            'roles' => $roles->map(fn ($r) => ['roleId' => (int) $r->roleId, 'roleName' => $r->roleName, 'label' => $r->label()])->values(),
            'status' => $u->status,
            'facilityId' => $u->facilityId,
            'facility' => $u->facility ? [
                'id' => $u->facility->id ?? null,
                'facilityId' => $u->facility->facilityId,
                'facilityName' => $u->facility->facilityName,
                'facilityCode' => $u->facility->facilityCode ?? null,
            ] : null,
            'allFacilities' => (bool) $u->allFacilities,
            'usesAssignedFacilities' => $assigned,
            'facilityIds' => $grants->pluck('facilityId')->map(fn ($v) => (int) $v)->values(),
            'facilities' => $grants->map(fn ($g) => ['facilityId' => (int) $g->facilityId, 'facilityName' => $g->facility->facilityName ?? "#{$g->facilityId}"])->values(),
            'organizationId' => $u->organization_id,
            'organizationName' => $u->organization->organizationName ?? null,
            'accessText' => $access,
            'canEdit' => $me ? $this->canChange($me, $u) : null,
            'createdAt' => $u->created_at,
            'updatedAt' => $u->updated_at,
        ];
    }

    /** Users the manager may see. */
    private function visibleUsersQuery(User $me)
    {
        $scope = $me->visibleFacilityIds();
        $manageable = $me->manageableRoleIds();
        $super = Role::where('roleName', 'NICRAT_SUPER_ADMIN')->value('roleId');
        $staffRoleIds = Role::where(fn ($q) => $q->whereNull('isStaffRole')->orWhere('isStaffRole', true))->pluck('roleId')->all();

        $q = User::query()
            ->whereIn('role', $staffRoleIds)
            ->when($super, fn ($x) => $x->where('role', '!=', $super)
                ->whereNotIn('id', DB::table('user_role_assignments')->select('userId')->where('roleId', $super)));

        if ($scope !== null) {
            // Limited managers: people in their facilities, holding only roles they may give.
            $q->where(fn ($w) => $w->whereIn('facilityId', $scope)
                    ->orWhereIn('id', UserFacilityGrant::query()->select('userId')->whereIn('facilityId', $scope)))
              ->whereIn('role', $manageable ?: [0])
              ->whereNotIn('id', DB::table('user_role_assignments')->select('userId')->whereNotIn('roleId', $manageable ?: [0]));
        }
        return $q;
    }

    /** GET /users */
    public function index(Request $request): JsonResponse
    {
        $me = Auth::user();
        if (!$me->canManageUsers()) return $this->deny('You do not have permission to view users');

        $query = $this->visibleUsersQuery($me)->with(['facility', 'user_role', 'assignedRoles', 'facilityGrants.facility', 'organization']);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('firstName', 'like', "%{$s}%")
                ->orWhere('lastName', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")
                ->orWhere('phoneNumber', 'like', "%{$s}%"));
        }
        if ($request->filled('facility') && $request->facility !== 'all') {
            $query->where('facilityId', $request->facility);
        }
        if ($request->filled('role') && $request->role !== 'all') {
            $rid = is_numeric($request->role) ? (int) $request->role : Role::where('roleName', $request->role)->value('roleId');
            $query->where(fn ($q) => $q->where('role', $rid)->orWhereIn('id', DB::table('user_role_assignments')->select('userId')->where('roleId', $rid)));
        }
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $list = $query->orderBy('firstName')->get();
        $users = $list->map(fn ($u) => $this->format($u, $me))->values();

        $all = $this->visibleUsersQuery($me)->with(['user_role', 'assignedRoles'])->get();
        $byRole = [];
        foreach ($all as $u) {
            foreach ($u->allRoles() as $r) {
                $byRole[$r->roleName] = ($byRole[$r->roleName] ?? 0) + 1;
            }
        }

        return response()->json([
            'status' => true,
            'users' => $users,
            'stats' => [
                'total' => $all->count(),
                'active' => $all->where('status', 'active')->count(),
                'inactive' => $all->where('status', 'inactive')->count(),
                'managers' => $all->filter(fn ($u) => $u->allRoles()->contains(fn ($r) => (bool) $r->canManageUsers))->count(),
                'byRole' => $byRole,
            ],
        ]);
    }

    /** GET /users/{user} */
    public function show(User $user): JsonResponse
    {
        $me = Auth::user();
        if (!$me->canManageUsers() || ($user->id !== $me->id && !$this->visibleUsersQuery($me)->whereKey($user->id)->exists())) {
            return $this->deny('Unauthorized access');
        }
        $user->load(['facility', 'user_role', 'assignedRoles', 'facilityGrants.facility', 'organization']);
        return response()->json(['status' => true, 'user' => $this->format($user, $me)]);
    }

    /**
     * Validates roles / facilities for a create or update and returns the clean values, or a JsonResponse error.
     * Accepts roles[] (first = main role) or the older single "role".
     */
    private function resolveAccess(Request $request, User $me, ?User $existing = null)
    {
        $roleIds = $request->input('roles');
        if (!is_array($roleIds) || !$roleIds) {
            $roleIds = $request->filled('role') ? [$request->input('role')] : ($existing ? $this->roleIdsOf($existing) : []);
        }
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        if (!$roleIds) return $this->deny('Choose at least one role.', 422);

        $roles = Role::whereIn('roleId', $roleIds)->get()->keyBy('roleId');
        if ($roles->count() !== count($roleIds)) return $this->deny('One of the roles no longer exists.', 422);

        $notAllowed = array_diff($roleIds, $me->manageableRoleIds());
        if ($notAllowed) {
            $names = $roles->only($notAllowed)->map(fn ($r) => $r->label())->implode(', ');
            return $this->deny("You are not allowed to give the role(s): {$names}.");
        }

        $facilityId = $request->has('facilityId') ? ($request->input('facilityId') ?: null) : $existing?->facilityId;
        $facilityIds = $request->has('facilityIds')
            ? array_values(array_unique(array_map('intval', array_filter((array) $request->input('facilityIds')))))
            : ($existing ? $existing->facilityGrants()->pluck('facilityId')->map(fn ($v) => (int) $v)->all() : []);
        $usesAssigned = $roles->contains(fn ($r) => $r->dataScopeType === 'assigned');
        $allFacilities = $usesAssigned && filter_var($request->input('allFacilities', $existing?->allFacilities ?? false), FILTER_VALIDATE_BOOLEAN);
        $orgId = $request->has('organizationId') ? ($request->input('organizationId') ?: null) : $existing?->organization_id;

        $scope = $me->visibleFacilityIds();
        // A manager limited to their facilities: people they add default to the manager's own facility.
        if ($scope !== null && !$facilityId && !$usesAssigned && $me->facilityId) {
            $facilityId = $me->facilityId;
        }

        $needsFacility = $roles->first(fn ($r) => $r->needsFacility());
        if ($needsFacility && !$facilityId) {
            return $this->deny("{$needsFacility->label()} must be assigned to a facility.", 422);
        }
        $needsOrg = $roles->first(fn ($r) => (bool) $r->requiresOrganization);
        if ($needsOrg && !$orgId) {
            return $this->deny("{$needsOrg->label()} must belong to an organisation.", 422);
        }
        if ($usesAssigned && !$allFacilities && !$facilityId && !$facilityIds) {
            return $this->deny('Choose "All facilities" or at least one facility for this person.', 422);
        }

        // Limited managers can only use facilities they can see, and can't give "all facilities".
        if ($scope !== null) {
            if ($allFacilities) return $this->deny('Only someone with country-wide access can give access to all facilities.');
            $outside = array_diff(array_map('intval', array_filter(array_merge([$facilityId], $facilityIds))), $scope);
            if ($outside) return $this->deny('You can only assign facilities you have access to.');
        }

        if ($facilityId && !Facility::where('facilityId', $facilityId)->exists()) return $this->deny('Facility not found.', 422);
        if ($orgId && class_exists(Organization::class) && !Organization::whereKey($orgId)->exists()) return $this->deny('Organisation not found.', 422);

        return [
            'roleIds' => $roleIds,
            'facilityId' => $facilityId,
            'facilityIds' => array_values(array_diff($facilityIds, [(int) $facilityId])),
            'allFacilities' => $allFacilities,
            'organizationId' => $needsOrg ? $orgId : null,
        ];
    }

    private function syncAccess(User $user, array $a, User $me): void
    {
        DB::table('user_role_assignments')->where('userId', $user->id)->whereNotIn('roleId', $a['roleIds'])->delete();
        foreach ($a['roleIds'] as $rid) {
            DB::table('user_role_assignments')->updateOrInsert(
                ['userId' => $user->id, 'roleId' => $rid],
                ['assignedBy' => $me->id, 'updated_at' => now(), 'created_at' => now()]
            );
        }
        UserFacilityGrant::where('userId', $user->id)->whereNotIn('facilityId', $a['facilityIds'] ?: [0])->delete();
        foreach ($a['facilityIds'] as $fid) {
            UserFacilityGrant::firstOrCreate(['userId' => $user->id, 'facilityId' => $fid], ['grantedBy' => $me->id]);
        }
        $user->unsetRelation('assignedRoles');
        $user->unsetRelation('facilityGrants');
        $user->forgetVisibleFacilities();
    }

    /** POST /users — password generated and emailed */
    public function store(Request $request): JsonResponse
    {
        $me = Auth::user();
        if (!$me->canCreateUsers()) return $this->deny('You do not have permission to create users');

        $v = Validator::make($request->all(), [
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phoneNumber' => 'required|string|max:20',
            'alternatePhoneNumber' => 'nullable|string|max:20',
            'roles' => 'nullable|array',
            'role' => 'nullable',
            'facilityId' => 'nullable',
            'facilityIds' => 'nullable|array',
            'allFacilities' => 'nullable',
            'organizationId' => 'nullable',
            'status' => 'sometimes|in:active,inactive',
        ]);
        if ($v->fails()) {
            return response()->json(['status' => false, 'message' => $v->errors()->first(), 'errors' => $v->errors()], 422);
        }

        $a = $this->resolveAccess($request, $me);
        if ($a instanceof JsonResponse) return $a;

        $plain = $this->generatePassword();
        $user = DB::transaction(function () use ($request, $a, $plain, $me) {
            $user = User::create([
                'firstName' => $request->firstName,
                'lastName' => $request->lastName,
                'email' => $request->email,
                'phoneNumber' => $request->phoneNumber,
                'alternatePhoneNumber' => $request->alternatePhoneNumber,
                'password' => Hash::make($plain),
                'role' => $a['roleIds'][0],
                'facilityId' => $a['facilityId'],
                'organization_id' => $a['organizationId'],
                'allFacilities' => $a['allFacilities'],
                'status' => $request->status ?? 'active',
            ]);
            $this->syncAccess($user, $a, $me);
            return $user;
        });

        $user->load(['facility', 'user_role', 'assignedRoles', 'facilityGrants.facility', 'organization']);
        $this->sendWelcomeEmail($user, $plain);
        $this->log('user_created', "Created user {$this->nameOf($user)} ({$user->email}) with role(s): " . $user->allRoles()->map->label()->implode(', ') . '.', $user);

        return response()->json([
            'status' => true,
            'message' => 'User created successfully. Login credentials have been sent to their email.',
            'user' => $this->format($user, $me),
        ], 201);
    }

    /** PUT /users/{id} */
    public function update(Request $request, $id): JsonResponse
    {
        $me = Auth::user();
        $user = User::with(['facility', 'user_role', 'assignedRoles', 'facilityGrants'])->findOrFail($id);
        $self = $user->id === $me->id;

        if (!$self && (!$me->canManageUsers() || !$this->canChange($me, $user))) {
            return $this->deny('You cannot edit this user.');
        }

        $v = Validator::make($request->all(), [
            'firstName' => 'sometimes|string|max:255',
            'lastName' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $id,
            'phoneNumber' => 'sometimes|string|max:20',
            'alternatePhoneNumber' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
            'roles' => 'nullable|array',
            'facilityIds' => 'nullable|array',
            'status' => 'sometimes|in:active,inactive',
        ]);
        if ($v->fails()) {
            return response()->json(['status' => false, 'message' => $v->errors()->first(), 'errors' => $v->errors()], 422);
        }

        $changesAccess = !$self && ($request->hasAny(['roles', 'role', 'facilityId', 'facilityIds', 'allFacilities', 'organizationId']));
        $a = null;
        if ($changesAccess) {
            $a = $this->resolveAccess($request, $me, $user);
            if ($a instanceof JsonResponse) return $a;
        }

        $before = ['roles' => $user->allRoles()->map->label()->all(), 'facilityId' => $user->facilityId, 'status' => $user->status];

        DB::transaction(function () use ($request, $user, $a, $me, $self) {
            $user->update([
                'firstName' => $request->firstName ?? $user->firstName,
                'lastName' => $request->lastName ?? $user->lastName,
                'email' => $request->email ?? $user->email,
                'phoneNumber' => $request->phoneNumber ?? $user->phoneNumber,
                'alternatePhoneNumber' => $request->has('alternatePhoneNumber') ? $request->alternatePhoneNumber : $user->alternatePhoneNumber,
                'status' => $self ? $user->status : ($request->status ?? $user->status),
            ] + ($a ? [
                'role' => $a['roleIds'][0],
                'facilityId' => $a['facilityId'],
                'organization_id' => $a['organizationId'],
                'allFacilities' => $a['allFacilities'],
            ] : []));
            if ($request->filled('password')) {
                $user->update(['password' => Hash::make($request->password)]);
            }
            if ($a) $this->syncAccess($user, $a, $me);
        });

        $user->refresh()->load(['facility', 'user_role', 'assignedRoles', 'facilityGrants.facility', 'organization']);
        $this->log('user_updated', "Updated user {$this->nameOf($user)} ({$user->email}).", $user, [
            'before' => $before,
            'after' => ['roles' => $user->allRoles()->map->label()->all(), 'facilityId' => $user->facilityId, 'status' => $user->status],
        ]);

        return response()->json(['status' => true, 'message' => 'User updated successfully', 'user' => $this->format($user, $me)]);
    }

    /** DELETE /users/{id} */
    public function destroy($id): JsonResponse
    {
        $me = Auth::user();
        $user = User::with('user_role')->findOrFail($id);
        if ($user->id === $me->id) return $this->deny('You cannot delete your own account');
        if (!$me->canManageUsers() || !$this->canChange($me, $user)) return $this->deny('You cannot delete this user.');

        $name = $this->nameOf($user);
        $email = $user->email;
        DB::transaction(function () use ($user) {
            DB::table('user_role_assignments')->where('userId', $user->id)->delete();
            UserFacilityGrant::where('userId', $user->id)->delete();
            $user->delete();
        });
        $this->log('user_deleted', "Deleted user {$name} ({$email}).", $user);

        return response()->json(['status' => true, 'message' => 'User deleted successfully']);
    }

    /**
     * GET /users/roles
     * roles    : the roles this person may give (with what each needs: facility, organisation, assigned facilities)
     * allRoles : every staff role (labels for filters and badges)
     * manager  : what the signed-in person can do
     */
    public function roles(): JsonResponse
    {
        $me = Auth::user();
        $staff = Role::where(fn ($q) => $q->whereNull('isStaffRole')->orWhere('isStaffRole', true))->orderBy('roleName')->get();
        $manageable = $me->manageableRoleIds();
        $scope = $me->visibleFacilityIds();

        return response()->json([
            'status' => true,
            'roles' => $staff->whereIn('roleId', $manageable)->map->toOption()->values(),
            'allRoles' => $staff->map->toOption()->values(),
            'manager' => [
                'canView' => $me->canManageUsers(),
                'canCreate' => !empty($manageable),
                'national' => $scope === null,
                'facilityId' => $me->facilityId,
                'canEditRoles' => $me->isSuperAdmin(),
            ],
        ]);
    }

    /** GET /users/facilities — facilities the signed-in manager can assign */
    public function facilities(): JsonResponse
    {
        $me = Auth::user();
        if (!$me->canManageUsers()) return response()->json(['status' => true, 'facilities' => []]);
        $scope = $me->visibleFacilityIds();
        $facilities = Facility::query()
            ->when($scope !== null, fn ($q) => $q->whereIn('facilityId', $scope))
            ->where(fn ($q) => $q->where('status', 'active')->orWhereNull('status'))
            ->orderBy('facilityName')
            ->get(['facilityId', 'facilityName', 'facilityCode', 'facilityState']);
        return response()->json(['status' => true, 'facilities' => $facilities]);
    }

    /** GET /users/organizations — for roles that belong to an organisation (e.g. Partner) */
    public function getOrganizations()
    {
        try {
            $organizations = Organization::where('status', true)
                ->select('id', 'organizationName', 'organizationCode', 'contactEmail')
                ->orderBy('organizationName')
                ->get();
            return response()->json(['status' => true, 'organizations' => $organizations, 'message' => 'Organizations fetched successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to fetch organizations'], 500);
        }
    }
}
