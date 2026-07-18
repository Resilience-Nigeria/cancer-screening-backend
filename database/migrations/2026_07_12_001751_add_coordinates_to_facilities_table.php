<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   // database/migrations/xxxx_add_coordinates_to_facilities_table.php
public function up(): void
{
    Schema::table('facilities', function (Blueprint $table) {
        $table->decimal('latitude',  10, 7)->nullable()->after('facilityAddress');
        $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
    });
}

public function down(): void
{
    Schema::table('facilities', function (Blueprint $table) {
        $table->dropColumn(['latitude', 'longitude']);
    });
}

 
};
