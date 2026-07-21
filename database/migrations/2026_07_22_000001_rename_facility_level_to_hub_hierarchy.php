<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Renames facilityLevel from the generic primary/secondary/tertiary
     * scheme (a placeholder used for the auto-referral feature) to the
     * actual named referral hierarchy: Feeder (e.g. PHC Gwagwa) -> SubHub
     * (e.g. Garki District Hospital) -> Hub (e.g. FMC Jabi). Only a Hub
     * may also be a treatment center — enforced in FacilityController,
     * not at the DB level, since that's a cross-column business rule.
     *
     * MySQL enum columns reject values outside the current definition,
     * so the enum must be widened to include both old and new values
     * before the data can be migrated, then narrowed afterward.
     */
    public function up(): void
    {
        DB::statement("
            ALTER TABLE `facilities`
            MODIFY `facilityLevel`
            ENUM('primary', 'secondary', 'tertiary', 'feeder', 'subhub', 'hub')
            NULL
        ");

        DB::table('facilities')->where('facilityLevel', 'primary')->update(['facilityLevel' => 'feeder']);
        DB::table('facilities')->where('facilityLevel', 'secondary')->update(['facilityLevel' => 'subhub']);
        DB::table('facilities')->where('facilityLevel', 'tertiary')->update(['facilityLevel' => 'hub']);

        DB::statement("
            ALTER TABLE `facilities`
            MODIFY `facilityLevel`
            ENUM('feeder', 'subhub', 'hub')
            NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE `facilities`
            MODIFY `facilityLevel`
            ENUM('primary', 'secondary', 'tertiary', 'feeder', 'subhub', 'hub')
            NULL
        ");

        DB::table('facilities')->where('facilityLevel', 'feeder')->update(['facilityLevel' => 'primary']);
        DB::table('facilities')->where('facilityLevel', 'subhub')->update(['facilityLevel' => 'secondary']);
        DB::table('facilities')->where('facilityLevel', 'hub')->update(['facilityLevel' => 'tertiary']);

        DB::statement("
            ALTER TABLE `facilities`
            MODIFY `facilityLevel`
            ENUM('primary', 'secondary', 'tertiary')
            NULL
        ");
    }
};
