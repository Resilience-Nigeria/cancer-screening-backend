<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Each column is added only if missing, so this is safe to re-run after a partial failure
        // (MySQL DDL is not transactional).
        Schema::table('prostate_screenings', function (Blueprint $table) {
            $add = function (string $col, callable $define) use ($table) {
                if (!Schema::hasColumn('prostate_screenings', $col)) {
                    $define($table);
                }
            };

            // Step 1 — eligibility
            $add('ageAtScreening', fn ($t) => $t->unsignedTinyInteger('ageAtScreening')->nullable());

            // Steps 2–3 — consent / counselling / shared decision
            $add('consentGiven', fn ($t) => $t->string('consentGiven', 3)->nullable());
            $add('counsellingDone', fn ($t) => $t->string('counsellingDone', 3)->nullable());
            $add('sharedDecision', fn ($t) => $t->string('sharedDecision', 16)->nullable());

            // Step 4 — primary tests
            $add('psaTestDate', fn ($t) => $t->date('psaTestDate')->nullable());
            $add('repeatPsaLevel', fn ($t) => $t->decimal('repeatPsaLevel', 8, 2)->nullable());
            $add('repeatPsaDate', fn ($t) => $t->date('repeatPsaDate')->nullable());
            $add('dreFinding', fn ($t) => $t->string('dreFinding', 16)->nullable());
            $add('previousPsaLevel', fn ($t) => $t->decimal('previousPsaLevel', 8, 2)->nullable());
            $add('previousPsaDate', fn ($t) => $t->date('previousPsaDate')->nullable());

            // Step 5 — recall
            $add('nextScreeningDate', fn ($t) => $t->date('nextScreeningDate')->nullable());

            // Step 7 — secondary risk assessment
            $add('prostateVolumeCc', fn ($t) => $t->decimal('prostateVolumeCc', 6, 1)->nullable());
            $add('psaDensity', fn ($t) => $t->decimal('psaDensity', 6, 3)->nullable());
            $add('freePsaPercent', fn ($t) => $t->decimal('freePsaPercent', 5, 1)->nullable());
            $add('psaVelocity', fn ($t) => $t->decimal('psaVelocity', 6, 2)->nullable());
            $add('mpMriResult', fn ($t) => $t->string('mpMriResult', 16)->nullable());
            $add('priorNegativeBiopsy', fn ($t) => $t->string('priorNegativeBiopsy', 3)->nullable());
            $add('highClinicalSuspicion', fn ($t) => $t->string('highClinicalSuspicion', 3)->nullable());
            $add('biopsyIndicated', fn ($t) => $t->string('biopsyIndicated', 3)->nullable());
            $add('biopsyIndicationReasons', fn ($t) => $t->string('biopsyIndicationReasons', 1000)->nullable());
        });

        // New values: screeningPathway += "Not Eligible"; psaEligibility += "Not Eligible", "Pending Consent".
        // Widen to VARCHAR. Raw SQL, run only after orphaned clientId rows have been cleaned up
        // (the table rebuild re-validates the clientId foreign key).
        DB::statement('ALTER TABLE `prostate_screenings` MODIFY `screeningPathway` VARCHAR(32) NULL');
        DB::statement('ALTER TABLE `prostate_screenings` MODIFY `psaEligibility` VARCHAR(32) NULL');
    }

    public function down(): void
    {
        $cols = array_values(array_filter([
            'ageAtScreening', 'consentGiven', 'counsellingDone', 'sharedDecision',
            'psaTestDate', 'repeatPsaLevel', 'repeatPsaDate', 'dreFinding',
            'previousPsaLevel', 'previousPsaDate', 'nextScreeningDate',
            'prostateVolumeCc', 'psaDensity', 'freePsaPercent', 'psaVelocity',
            'mpMriResult', 'priorNegativeBiopsy', 'highClinicalSuspicion',
            'biopsyIndicated', 'biopsyIndicationReasons',
        ], fn ($c) => Schema::hasColumn('prostate_screenings', $c)));

        if ($cols) {
            Schema::table('prostate_screenings', fn (Blueprint $t) => $t->dropColumn($cols));
        }
    }
};