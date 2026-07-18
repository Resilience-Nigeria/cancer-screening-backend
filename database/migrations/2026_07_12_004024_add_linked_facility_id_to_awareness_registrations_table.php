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
    Schema::table('awareness_registrations', function (Blueprint $table) {
        $table->unsignedBigInteger('linkedFacilityId')->nullable()->after('status');
        $table->foreign('linkedFacilityId')
              ->references('facilityId')
              ->on('facilities')
              ->nullOnDelete();
    });
}

public function down(): void
{
    Schema::table('awarenessRegistrations', function (Blueprint $table) {
        $table->dropForeign(['linkedFacilityId']);
        $table->dropColumn('linkedFacilityId');
    });
}
};
