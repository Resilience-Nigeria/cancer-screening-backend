<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Relocates breastfeeding history and previous breast surgery history
     * from breast_screenings (a procedure-specific table) into the
     * general Risk Profile, where they belong alongside ageAtFirstMenstruation
     * and ageAtMenopause. The breast_screenings columns are left in place
     * (nullable, unused going forward) rather than dropped, so existing
     * historical screening records aren't destroyed.
     */
    public function up(): void
    {
        Schema::table('client_risk_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('client_risk_profiles', 'breastfeedingHistory')) {
                $table->string('breastfeedingHistory')->nullable();
            }
            if (!Schema::hasColumn('client_risk_profiles', 'breastfeedingDuration')) {
                $table->unsignedSmallInteger('breastfeedingDuration')->nullable();
            }
            if (!Schema::hasColumn('client_risk_profiles', 'previousBreastSurgery')) {
                $table->string('previousBreastSurgery')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('client_risk_profiles', function (Blueprint $table) {
            foreach (['breastfeedingHistory', 'breastfeedingDuration', 'previousBreastSurgery'] as $col) {
                if (Schema::hasColumn('client_risk_profiles', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
