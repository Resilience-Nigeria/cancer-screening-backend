<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles become configuration instead of code, and a user can hold several roles and several facilities.
 *
 * roles (new columns)
 *   roleLabel             display name ("NICRAT Staff")
 *   isSystem              built-in roles the code relies on; can be edited, not deleted or renamed
 *   isStaffRole           false for CLIENT (portal login) — never offered on the Users page
 *   requiresOrganization  e.g. Partner: pick an organisation instead of a facility
 *   canManageUsers        can open User Management
 *   manageableRoleIds     the roles this role may give to other people ([] = view only)
 *   dataScopeType         gains "assigned": sees only the facilities given to the person (or all, per person)
 *
 * users.allFacilities      for "assigned" roles: this person sees every facility
 * user_role_assignments    extra roles per user (users.role stays the main role)
 * Extra facilities per user keep using user_facility_grants.
 *
 * Seeded to match today's behaviour, plus the new NICRAT Staff role and NICRAT Admin = country-wide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'roleLabel')) $table->string('roleLabel')->nullable();
            if (!Schema::hasColumn('roles', 'isSystem')) $table->boolean('isSystem')->default(false);
            if (!Schema::hasColumn('roles', 'isStaffRole')) $table->boolean('isStaffRole')->default(true);
            if (!Schema::hasColumn('roles', 'requiresOrganization')) $table->boolean('requiresOrganization')->default(false);
            if (!Schema::hasColumn('roles', 'canManageUsers')) $table->boolean('canManageUsers')->default(false);
            if (!Schema::hasColumn('roles', 'manageableRoleIds')) $table->json('manageableRoleIds')->nullable();
            if (!Schema::hasColumn('roles', 'dataScopeType')) $table->string('dataScopeType', 30)->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'allFacilities')) $table->boolean('allFacilities')->default(false);
        });

        if (!Schema::hasTable('user_role_assignments')) {
            Schema::create('user_role_assignments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('userId')->index();
                $table->unsignedBigInteger('roleId')->index();
                $table->unsignedBigInteger('assignedBy')->nullable();
                $table->timestamps();
                $table->unique(['userId', 'roleId']);
            });
        }

        $now = now();

        // New role: NICRAT Staff — sees all facilities or only the ones given to them.
        if (!DB::table('roles')->where('roleName', 'NICRAT_STAFF')->exists()) {
            $row = [
                'roleName' => 'NICRAT_STAFF',
                'roleDescription' => 'NICRAT staff. Each person sees all facilities or only the facilities assigned to them.',
                'dataScopeType' => 'assigned',
            ];
            // roleId may not auto-increment in every install: give it the next number.
            $row['roleId'] = ((int) DB::table('roles')->max('roleId')) + 1;
            if (Schema::hasColumn('roles', 'created_at')) {
                $row['created_at'] = $now;
                $row['updated_at'] = $now;
            }
            DB::table('roles')->insert($row);
        }

        $ids = DB::table('roles')->pluck('roleId', 'roleName');
        $id = fn (array $names) => array_values(array_filter(array_map(fn ($n) => isset($ids[$n]) ? (int) $ids[$n] : null, $names)));

        $config = [
            'NICRAT_SUPER_ADMIN' => ['roleLabel' => 'NICRAT Super Admin', 'dataScopeType' => 'national', 'canManageUsers' => true,
                'manageableRoleIds' => $id(['NICRAT_ADMIN', 'NICRAT_STAFF', 'NAVIGATOR', 'HOSPITAL_ADMIN', 'NURSE', 'DOCTOR', 'PARTNER'])],
            'NICRAT_ADMIN' => ['roleLabel' => 'NICRAT Admin', 'dataScopeType' => 'national', 'canManageUsers' => true, 'manageableRoleIds' => []],
            'NICRAT_STAFF' => ['roleLabel' => 'NICRAT Staff', 'dataScopeType' => 'assigned', 'canManageUsers' => false, 'manageableRoleIds' => []],
            'NAVIGATOR' => ['roleLabel' => 'Navigator', 'canManageUsers' => true, 'manageableRoleIds' => $id(['NURSE', 'DOCTOR'])],
            'HOSPITAL_ADMIN' => ['roleLabel' => 'Hospital Admin', 'canManageUsers' => true, 'manageableRoleIds' => $id(['NURSE', 'DOCTOR'])],
            'NURSE' => ['roleLabel' => 'Nurse / Desk Officer'],
            'DOCTOR' => ['roleLabel' => 'Doctor'],
            'PARTNER' => ['roleLabel' => 'Partner', 'requiresOrganization' => true],
            'CLIENT' => ['roleLabel' => 'Client', 'isStaffRole' => false],
        ];
        foreach ($config as $name => $values) {
            $values['isSystem'] = true;
            if (isset($values['manageableRoleIds'])) $values['manageableRoleIds'] = json_encode($values['manageableRoleIds']);
            DB::table('roles')->where('roleName', $name)->update($values);
        }
        // Any other existing role: label from its name.
        foreach (DB::table('roles')->whereNull('roleLabel')->get(['roleId', 'roleName']) as $r) {
            DB::table('roles')->where('roleId', $r->roleId)->update(['roleLabel' => ucwords(strtolower(str_replace('_', ' ', $r->roleName)))]);
        }

        // Every user's main role is also in the assignments table.
        $rows = DB::table('users')->whereNotNull('role')->get(['id', 'role'])
            ->filter(fn ($u) => is_numeric($u->role))
            ->map(fn ($u) => ['userId' => $u->id, 'roleId' => (int) $u->role, 'created_at' => $now, 'updated_at' => $now])
            ->values()->all();
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('user_role_assignments')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role_assignments');
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'allFacilities')) $table->dropColumn('allFacilities');
        });
        Schema::table('roles', function (Blueprint $table) {
            foreach (['roleLabel', 'isSystem', 'isStaffRole', 'requiresOrganization', 'canManageUsers', 'manageableRoleIds'] as $c) {
                if (Schema::hasColumn('roles', $c)) $table->dropColumn($c);
            }
        });
        DB::table('roles')->where('roleName', 'NICRAT_STAFF')->delete();
    }
};
