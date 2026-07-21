<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Hospital Admin: creates and manages hospital staff (Nurses,
     * Doctors) at their own facility. Functionally similar to Navigator
     * for staff-management purposes (both are handled identically in
     * UserController), but kept as a distinct role so organizations can
     * use whichever title fits their structure, and so the two can
     * diverge in capability later without another migration.
     */
    public function up(): void
    {
        if (!DB::table('roles')->where('roleName', 'HOSPITAL_ADMIN')->exists()) {
            DB::table('roles')->insert([
                'roleName' => 'HOSPITAL_ADMIN',
                'roleDescription' => 'Facility-level administrator — creates and manages hospital staff (Nurses, Doctors) at their own facility.',
                'dataScopeType' => 'facility_only',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('roleName', 'HOSPITAL_ADMIN')->delete();
    }
};
