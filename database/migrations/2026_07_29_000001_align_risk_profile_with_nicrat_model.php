<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Aligns client_risk_profiles with the NICRAT Cancer Risk
     * Stratification Model (lifestyle score from BMI/smoking/alcohol/
     * physical activity + HIV status -> low/intermediate/high cancer
     * risk category) and adds socio-economic classification.
     *
     * Also fixes a real, currently-live bug: smokingStatus and
     * alcoholConsumption were `enum` columns whose allowed values didn't
     * match what the frontend form actually sends (e.g. the form sends
     * 'non_smoker' and 'weekly'/'occasionally'/'regularly'/'daily', but
     * the enum only allowed 'never' and 'none'/'occasional'/'regular') —
     * meaning most real submissions would fail to save. Converting these
     * to plain strings removes this whole class of drift-based bugs;
     * Laravel-level validation (not a DB enum) is the right place to
     * enforce the allowed value set going forward.
     */
    public function up(): void
    {
        Schema::table('client_risk_profiles', function (Blueprint $table) {
            $table->string('physicalActivityLevel')->nullable()->after('alcoholConsumption');
            $table->string('occupationCategory')->nullable()->after('hivStatus');

            // Computed by CancerRiskStratificationService on save — stored
            // so analytics/reporting can query by risk category without
            // recomputing, and so the value is visible on the record even
            // if the scoring rules change later (historical accuracy).
            $table->unsignedTinyInteger('lifestyleRiskScore')->nullable();
            $table->unsignedTinyInteger('hivRiskScore')->nullable();
            $table->unsignedTinyInteger('totalCancerRiskScore')->nullable();
            $table->enum('cancerRiskCategory', ['low', 'intermediate', 'high'])->nullable();
            $table->unsignedTinyInteger('socioeconomicScore')->nullable();
            $table->enum('socioeconomicClass', ['upper', 'middle', 'lower'])->nullable();
        });

        // Convert the brittle enum columns to plain strings. MySQL
        // requires an explicit type change; existing values (which
        // already satisfy their old enum, or — as established above —
        // may have failed to save at all) are preserved as-is.
        DB::statement("ALTER TABLE client_risk_profiles MODIFY smokingStatus VARCHAR(50) NULL");
        DB::statement("ALTER TABLE client_risk_profiles MODIFY alcoholConsumption VARCHAR(50) NULL");
    }

    public function down(): void
    {
        Schema::table('client_risk_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'physicalActivityLevel', 'occupationCategory', 'lifestyleRiskScore',
                'hivRiskScore', 'totalCancerRiskScore', 'cancerRiskCategory',
                'socioeconomicScore', 'socioeconomicClass',
            ]);
        });
    }
};
