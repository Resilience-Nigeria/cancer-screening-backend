<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Renames existing roles to the new 7-role scheme:
     * SUPER_ADMIN -> NICRAT_SUPER_ADMIN, NICRAT_STAFF -> NICRAT_ADMIN,
     * HOSPITAL_ADMIN -> NAVIGATOR, DATA_CLERK -> NURSE, PARTNER unchanged.
     * Adds DOCTOR and CLIENT as new roles (Client is not a staff role —
     * it authenticates through a separate portal, not app/Models/User —
     * but still needs a `roles` row for consistency/reporting).
     *
     * This only updates the roleName string on existing rows, so every
     * user's roleId (and therefore their account) is untouched.
     */
    public function up(): void
    {
        $renames = [
            'SUPER_ADMIN' => 'NICRAT_SUPER_ADMIN',
            'NICRAT_STAFF' => 'NICRAT_ADMIN',
            'HOSPITAL_ADMIN' => 'NAVIGATOR',
            'DATA_CLERK' => 'NURSE',
        ];

        foreach ($renames as $old => $new) {
            DB::table('roles')->where('roleName', $old)->update(['roleName' => $new]);
        }

        $newRoles = [
            ['roleName' => 'DOCTOR', 'roleDescription' => 'Facility-level physician — clinical authority beyond nurse-level screening (diagnosis confirmation, treatment decisions).'],
            ['roleName' => 'CLIENT', 'roleDescription' => 'Patient/client self-service portal access — scoped to their own record only.'],
        ];

        foreach ($newRoles as $role) {
            if (!DB::table('roles')->where('roleName', $role['roleName'])->exists()) {
                DB::table('roles')->insert([
                    'roleName' => $role['roleName'],
                    'roleDescription' => $role['roleDescription'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $renames = [
            'NICRAT_SUPER_ADMIN' => 'SUPER_ADMIN',
            'NICRAT_ADMIN' => 'NICRAT_STAFF',
            'NAVIGATOR' => 'HOSPITAL_ADMIN',
            'NURSE' => 'DATA_CLERK',
        ];

        foreach ($renames as $old => $new) {
            DB::table('roles')->where('roleName', $old)->update(['roleName' => $new]);
        }

        DB::table('roles')->whereIn('roleName', ['DOCTOR', 'CLIENT'])->delete();
    }
};
