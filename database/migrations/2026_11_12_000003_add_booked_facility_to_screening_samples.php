<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screening_samples', function (Blueprint $table) {
            if (!Schema::hasColumn('screening_samples', 'bookedFacilityId')) {
                $table->unsignedBigInteger('bookedFacilityId')->nullable()->index()->after('facilityId');
            }
        });
    }

    public function down(): void
    {
        Schema::table('screening_samples', function (Blueprint $table) {
            $table->dropColumn('bookedFacilityId');
        });
    }
};
