<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nurses and navigators see only their own facility's records.
 * A role with no data scope configured is treated as "national" by User::visibleFacilityIds(), so these two
 * roles are set to facility_only explicitly. (Settings → Role Data Scope shows the same value.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('roles', 'dataScopeType')) return;
        DB::table('roles')->whereIn(DB::raw('UPPER(roleName)'), ['NURSE', 'NAVIGATOR'])->update(['dataScopeType' => 'facility_only']);
    }

    public function down(): void
    {
        // Not reversed automatically: set the scope back in Settings → Role Data Scope if needed.
    }
};
