<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // database/migrations/xxxx_add_is_active_to_facilities_table.php
public function up(): void
{
    Schema::table('facilities', function (Blueprint $table) {
        $table->boolean('isActive')->default(true)->after('whatsappNumber');
    });
}

public function down(): void
{
    Schema::table('facilities', function (Blueprint $table) {
        $table->dropColumn('isActive');
    });
}

};
