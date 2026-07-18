<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * breast_screenings has a unique(visitId) constraint — one row per visit —
     * so laterality is captured as side-specific columns on that same row
     * rather than splitting into separate left/right rows. This avoids a
     * larger structural change while still letting CBE and imaging findings
     * be recorded independently per side, as required for accurate biopsy
     * and referral tracking.
     */
    public function up(): void
    {
        Schema::table('breast_screenings', function (Blueprint $table) {
            $table->enum('leftCbeFinding', ['normal', 'suspicious'])->nullable()->after('method');
            $table->enum('rightCbeFinding', ['normal', 'suspicious'])->nullable()->after('leftCbeFinding');

            $table->string('leftBiradsScore')->nullable()->after('biradsScore');
            $table->string('rightBiradsScore')->nullable()->after('leftBiradsScore');

            $table->string('leftBreastDensity')->nullable()->after('breastDensity');
            $table->string('rightBreastDensity')->nullable()->after('leftBreastDensity');

            $table->enum('leftImagingFinding', ['normal', 'suspicious'])->nullable()->after('rightBreastDensity');
            $table->enum('rightImagingFinding', ['normal', 'suspicious'])->nullable()->after('leftImagingFinding');
        });
    }

    public function down(): void
    {
        Schema::table('breast_screenings', function (Blueprint $table) {
            $table->dropColumn([
                'leftCbeFinding', 'rightCbeFinding',
                'leftBiradsScore', 'rightBiradsScore',
                'leftBreastDensity', 'rightBreastDensity',
                'leftImagingFinding', 'rightImagingFinding',
            ]);
        });
    }
};
