<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the columns needed for the National Prostate Cancer Screening
     * Algorithm: the 3 additional red-flag symptoms, the symptomatic/
     * asymptomatic routing flag, the PSA-deferment conditions + eligibility
     * outcome, and the free-text recommended-action summary.
     *
     * Prostate-only — no other screening table is touched.
     */
    public function up(): void
    {
        Schema::table('prostate_screenings', function (Blueprint $table) {
            // Additional red-flag symptoms
            $table->enum('inabilityToPassUrine', ['yes', 'no'])->nullable()->after('bloodInUrine');
            $table->enum('bonePainHipBack', ['yes', 'no'])->nullable()->after('inabilityToPassUrine');
            $table->enum('unexplainedWeightLoss', ['yes', 'no'])->nullable()->after('bonePainHipBack');

            // Screening pathway routing
            $table->enum('screeningPathway', ['Symptomatic', 'Asymptomatic'])->nullable()->after('unexplainedWeightLoss');

            // PSA-deferment conditions
            $table->enum('recentDre', ['yes', 'no'])->nullable()->after('screeningPathway');
            $table->enum('recentEjaculation', ['yes', 'no'])->nullable()->after('recentDre');
            $table->enum('recentUrinaryInfection', ['yes', 'no'])->nullable()->after('recentEjaculation');
            $table->enum('recentVigorousExercise', ['yes', 'no'])->nullable()->after('recentUrinaryInfection');
            $table->enum('psaEligibility', ['Eligible', 'Deferred'])->nullable()->after('recentVigorousExercise');
            $table->date('recallDate')->nullable()->after('psaEligibility');
            $table->string('recommendedAction', 1000)->nullable()->after('recallDate');
        });
    }

    public function down(): void
    {
        Schema::table('prostate_screenings', function (Blueprint $table) {
            $table->dropColumn([
                'inabilityToPassUrine',
                'bonePainHipBack',
                'unexplainedWeightLoss',
                'screeningPathway',
                'recentDre',
                'recentEjaculation',
                'recentUrinaryInfection',
                'recentVigorousExercise',
                'psaEligibility',
                'recallDate',
                'recommendedAction',
            ]);
        });
    }
};