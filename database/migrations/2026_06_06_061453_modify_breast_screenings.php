<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('breast_screenings', function (Blueprint $table) {
            $t = 'breast_screenings';

            if (!Schema::hasColumn($t, 'breastfeedingHistory')) {
                $table->string('breastfeedingHistory')->nullable();
            }
            if (!Schema::hasColumn($t, 'breastfeedingDuration')) {
                $table->unsignedSmallInteger('breastfeedingDuration')->nullable();
            }
            if (!Schema::hasColumn($t, 'breastLumps')) {
                $table->string('breastLumps')->nullable();
            }
            if (!Schema::hasColumn($t, 'breastNippleDischarge')) {
                $table->string('breastNippleDischarge')->nullable();
            }
            if (!Schema::hasColumn($t, 'dischargeType')) {
                $table->string('dischargeType')->nullable();
            }
            if (!Schema::hasColumn($t, 'skinChanges')) {
                $table->string('skinChanges')->nullable();
            }
            if (!Schema::hasColumn($t, 'breastPain')) {
                $table->string('breastPain')->nullable();
            }
            if (!Schema::hasColumn($t, 'previousBreastSurgery')) {
                $table->string('previousBreastSurgery')->nullable();
            }
            if (!Schema::hasColumn($t, 'previousBiopsy')) {
                $table->string('previousBiopsy')->nullable();
            }
            if (!Schema::hasColumn($t, 'ageAtFirstMenstruation')) {
                $table->unsignedTinyInteger('ageAtFirstMenstruation')->nullable();
            }
            if (!Schema::hasColumn($t, 'ageAtMenopause')) {
                $table->unsignedTinyInteger('ageAtMenopause')->nullable();
            }
            if (!Schema::hasColumn($t, 'biopsyResult')) {
                $table->string('biopsyResult')->nullable();
            }
            if (!Schema::hasColumn($t, 'referralCompleted')) {
                $table->boolean('referralCompleted')->default(false);
            }
            if (!Schema::hasColumn($t, 'remarks')) {
                $table->string('remarks', 2000)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('breast_screenings', function (Blueprint $table) {
            foreach ([
                'breastfeedingHistory',
                'breastfeedingDuration',
                'breastLumps',
                'breastNippleDischarge',
                'dischargeType',
                'skinChanges',
                'breastPain',
                'previousBreastSurgery',
                'previousBiopsy',
                'ageAtFirstMenstruation',
                'ageAtMenopause',
                'biopsyResult',
                'referralCompleted',
                'remarks',
            ] as $col) {
                if (Schema::hasColumn('breast_screenings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};