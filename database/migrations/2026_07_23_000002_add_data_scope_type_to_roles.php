<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * dataScopeType controls how much of the system a role can see:
     *   - national: everything, no restriction
     *   - state: all facilities in the same state as the user's own facility
     *   - hub_hierarchy: the user's Hub (or the Hub above their facility)
     *     plus every SubHub/Feeder under it
     *   - subhub_hierarchy: the user's SubHub (or the SubHub above their
     *     facility) plus every Feeder under it
     *   - facility_only: just the user's own assigned facility
     *
     * This is role-level configuration (everyone with a given role shares
     * the same scope TYPE), but the actual set of visible facility IDs is
     * still resolved per-user from their own assigned facility — two
     * Navigators at different Hubs see different hierarchies even though
     * both have "hub_hierarchy" scope.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `roles`
            ADD COLUMN `dataScopeType`
            ENUM('national', 'state', 'hub_hierarchy', 'subhub_hierarchy', 'facility_only')
            NULL
            AFTER `roleDescription`
        ");

        $defaults = [
            'NICRAT_SUPER_ADMIN' => 'national',
            'NICRAT_ADMIN' => 'national',
            'PARTNER' => 'national',
            'NAVIGATOR' => 'hub_hierarchy',
            'NURSE' => 'facility_only',
            'DOCTOR' => 'facility_only',
            // CLIENT has no data-scope concept — the client portal is
            // scoped to exactly one client's own record, a completely
            // separate mechanism (ClientTokenAuth), not this facility
            // hierarchy at all.
        ];

        foreach ($defaults as $roleName => $scope) {
            DB::table('roles')->where('roleName', $roleName)->update(['dataScopeType' => $scope]);
        }
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `roles` DROP COLUMN `dataScopeType`");
    }
};
