<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Registers the new Facility Map page in menu_visibility_rules so
     * it shows up in the Settings > Menu Visibility matrix like every
     * other nav item, rather than being invisible to that admin UI.
     * Defaults to visible-to-everyone (allowedRoles = null).
     */
    public function up(): void
    {
        $exists = DB::table('menu_visibility_rules')->where('menuKey', '/ncsr/facility-map')->exists();

        if (!$exists) {
            DB::table('menu_visibility_rules')->insert([
                'menuKey' => '/ncsr/facility-map',
                'menuLabel' => 'Facility Map',
                'allowedRoles' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('menu_visibility_rules')->where('menuKey', '/ncsr/facility-map')->delete();
    }
};
