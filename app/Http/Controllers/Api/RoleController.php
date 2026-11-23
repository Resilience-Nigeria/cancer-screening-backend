<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Roles page (Super Admin only): create, edit and delete roles, and set what each one allows —
 * data scope, whether it needs a facility or an organisation, whether it can manage users and
 * which roles it may give. Built-in (system) roles can be edited but not renamed or deleted.
 */
class RoleController extends Controller
{
    private const SCOPES = ['national', 'state', 'hub_hierarchy', 'subhub_hierarchy', 'facility_only', 'assigned'];

    private function superAdmin()
    {
        $me = Auth::user();
        abort_unless($me && $me->isSuperAdmin(), 403, 'Only a Super Admin can manage roles.');
        return $me;
    }

    private function log(string $text, array $details = []): void
    {
        if (class_exists(ActivityLog::class)) {
            ActivityLog::record('role_changed', $text, ['details' => $details]);
        }
    }

    private function userCounts(): array
    {
        $main = DB::table('users')->select('role', DB::raw('COUNT(*) as n'))->groupBy('role')->pluck('n', 'role')->all();
        $extra = DB::table('user_role_assignments')
            ->join('users', 'users.id', '=', 'user_role_assignments.userId')
            ->whereColumn('users.role', '!=', 'user_role_assignments.roleId')
            ->select('user_role_assignments.roleId', DB::raw('COUNT(*) as n'))
            ->groupBy('user_role_assignments.roleId')->pluck('n', 'roleId')->all();
        $out = [];
        foreach ($main + $extra as $id => $_) {
            $out[(int) $id] = (int) ($main[$id] ?? 0) + (int) ($extra[$id] ?? 0);
        }
        return $out;
    }

    /** GET /roles */
    public function index(): JsonResponse
    {
        $this->superAdmin();
        $counts = $this->userCounts();
        $roles = Role::orderByDesc('isSystem')->orderBy('roleName')->get()->map(fn ($r) => $r->toOption() + [
            'userCount' => $counts[(int) $r->roleId] ?? 0,
        ]);
        return response()->json(['status' => true, 'roles' => $roles]);
    }

    private function rules(?Role $role = null): array
    {
        return [
            'label' => ['required', 'string', 'max:100'],
            'roleDescription' => ['nullable', 'string', 'max:500'],
            'dataScopeType' => ['required', Rule::in(self::SCOPES)],
            'requiresOrganization' => ['boolean'],
            'canManageUsers' => ['boolean'],
            'manageableRoleIds' => ['nullable', 'array'],
            'manageableRoleIds.*' => ['integer', 'exists:roles,roleId'],
        ];
    }

    private function apply(Role $role, array $data): void
    {
        $role->roleLabel = $data['label'];
        $role->roleDescription = $data['roleDescription'] ?? null;
        $role->dataScopeType = $data['dataScopeType'];
        $role->requiresOrganization = (bool) ($data['requiresOrganization'] ?? false);
        $role->canManageUsers = (bool) ($data['canManageUsers'] ?? false);
        $ids = array_values(array_unique(array_map('intval', $data['manageableRoleIds'] ?? [])));
        // Nobody can be given the power to create Super Admins.
        $super = Role::where('roleName', 'NICRAT_SUPER_ADMIN')->value('roleId');
        $role->manageableRoleIds = array_values(array_filter($ids, fn ($i) => $i !== (int) $super));
    }

    /** POST /roles */
    public function store(Request $request): JsonResponse
    {
        $this->superAdmin();
        $data = $request->validate($this->rules());

        $name = Str::upper(Str::snake(preg_replace('/[^A-Za-z0-9 ]/', ' ', $data['label'])));
        $name = preg_replace('/_+/', '_', trim($name, '_'));
        if (!$name) return response()->json(['message' => 'Give the role a name.'], 422);
        if (Role::where('roleName', $name)->exists()) {
            return response()->json(['message' => "A role called {$name} already exists."], 422);
        }

        $role = new Role();
        $role->roleId = ((int) Role::max('roleId')) + 1;
        $role->roleName = $name;
        $role->isSystem = false;
        $role->isStaffRole = true;
        $this->apply($role, $data);
        $role->save();

        $this->log("Created role {$role->label()}.", ['role' => $role->toOption()]);
        return response()->json(['status' => true, 'message' => 'Role created.', 'role' => $role->toOption() + ['userCount' => 0]], 201);
    }

    /** PUT /roles/{role} */
    public function update(Request $request, Role $role): JsonResponse
    {
        $this->superAdmin();
        if ($role->roleName === 'NICRAT_SUPER_ADMIN') {
            // The Super Admin role must always see and manage everything.
            $request->merge(['dataScopeType' => 'national', 'canManageUsers' => true]);
        }
        $data = $request->validate($this->rules($role));
        $before = $role->toOption();
        $this->apply($role, $data);
        $role->save();

        $this->log("Changed role {$role->label()}.", ['before' => $before, 'after' => $role->toOption()]);
        return response()->json(['status' => true, 'message' => 'Role saved.', 'role' => $role->toOption()]);
    }

    /** PATCH /roles/{role}/scope — kept for older screens */
    public function updateScope(Request $request, Role $role): JsonResponse
    {
        $this->superAdmin();
        $data = $request->validate(['dataScopeType' => ['required', Rule::in(self::SCOPES)]]);
        if ($role->roleName === 'NICRAT_SUPER_ADMIN' && $data['dataScopeType'] !== 'national') {
            return response()->json(['message' => 'The Super Admin role always sees all facilities.'], 422);
        }
        $old = $role->dataScopeType;
        $role->update(['dataScopeType' => $data['dataScopeType']]);
        $this->log("Changed the data scope of {$role->label()} from {$old} to {$role->dataScopeType}.");
        return response()->json(['status' => true, 'role' => $role]);
    }

    /** DELETE /roles/{role} — custom roles with nobody in them */
    public function destroy(Role $role): JsonResponse
    {
        $this->superAdmin();
        if ($role->isSystem) {
            return response()->json(['message' => 'Built-in roles can\'t be deleted. You can change what they allow instead.'], 422);
        }
        $count = $this->userCounts()[(int) $role->roleId] ?? 0;
        if ($count > 0) {
            return response()->json(['message' => "{$count} user(s) still have this role. Give them another role first."], 422);
        }
        $label = $role->label();
        DB::transaction(function () use ($role) {
            DB::table('user_role_assignments')->where('roleId', $role->roleId)->delete();
            // Remove it from other roles' "may give" lists.
            foreach (Role::whereNotNull('manageableRoleIds')->get() as $r) {
                $ids = array_values(array_filter($r->manageableRoleIds ?? [], fn ($i) => (int) $i !== (int) $role->roleId));
                if ($ids !== ($r->manageableRoleIds ?? [])) {
                    $r->manageableRoleIds = $ids;
                    $r->save();
                }
            }
            if (DB::getSchemaBuilder()->hasTable('report_recipients')) {
                DB::table('report_recipients')->where('type', 'role')->where('roleName', $role->roleName)->delete();
            }
            $role->delete();
        });
        $this->log("Deleted role {$label}.");
        return response()->json(['status' => true, 'message' => 'Role deleted.']);
    }
}
