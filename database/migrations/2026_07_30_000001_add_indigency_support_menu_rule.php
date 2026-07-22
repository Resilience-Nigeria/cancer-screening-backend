<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $exists = DB::table('menu_visibility_rules')->where('menuKey', '/ncsr/indigency-support')->exists();

        if (!$exists) {
            DB::table('menu_visibility_rules')->insert([
                'menuKey' => '/ncsr/indigency-support',
                'menuLabel' => 'Indigency Support',
                'allowedRoles' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('menu_visibility_rules')->where('menuKey', '/ncsr/indigency-support')->delete();
    }
};
