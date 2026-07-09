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
    Schema::table('clients', function (Blueprint $table) {
        $table->enum('journeyStage', [
            'awareness',
            'screening',
            'confirmation',
            'treatment',
            'followup',
        ])->default('awareness')->after('screeningCategory');

        $table->unsignedBigInteger('linkedFacilityId')->nullable()->after('journeyStage');
        $table->foreign('linkedFacilityId')->references('facilityId')->on('facilities')->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('clients', function (Blueprint $table) {
        $table->dropForeign(['linkedFacilityId']);
        $table->dropColumn(['journeyStage', 'linkedFacilityId']);
    });
}
    

  
};
