<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::table('facilities', function (Blueprint $table) {
        $table->json('facilityLevels')->nullable()->after('facilityLevel');
    });

    // Backfill from the existing single tier
    DB::statement("UPDATE facilities SET facilityLevels = JSON_ARRAY(facilityLevel) WHERE facilityLevel IS NOT NULL");
}

public function down(): void
{
    Schema::table('facilities', fn (Blueprint $table) => $table->dropColumn('facilityLevels'));
}

 
};
