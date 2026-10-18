<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Standardized cervical form — §3/§4 (visit details), §4 (risk profile) and §5 (screening history).
 *
 *  - Visit-level fields (reason for visit, referred-in, entry point) go on the visits table: they
 *    describe this encounter, and a client can arrive through a different entry point next time.
 *  - Everything else goes on the risk-profile table, which the wizard already posts to.
 *
 * Table names are resolved from the Eloquent models so they don't have to be guessed. If your model
 * classes are named differently, change RISK_MODEL / VISIT_MODEL below.
 *
 * Every column is added only if missing, so the migration is safe to re-run. No MODIFY statements,
 * so it does not re-validate foreign keys.
 */
return new class extends Migration
{
    private const RISK_MODEL = \App\Models\RiskProfile::class;
    private const VISIT_MODEL = \App\Models\ScreeningVisit::class;

    private function table(string $model, string $fallback): string
    {
        return class_exists($model) ? (new $model)->getTable() : $fallback;
    }

    public function up(): void
    {
        $riskTable = $this->table(self::RISK_MODEL, 'client_risk_profiles');
        $visitTable = $this->table(self::VISIT_MODEL, 'screening_visits');

        foreach ([$riskTable, $visitTable] as $t) {
            if (!Schema::hasTable($t)) {
                throw new RuntimeException("Table '{$t}' not found. Update RISK_MODEL / VISIT_MODEL in this migration.");
            }
        }

        // ── Visit-level fields ──────────────────────────────────────────
        Schema::table($visitTable, function (Blueprint $table) use ($visitTable) {
            $add = function (string $col, callable $define) use ($table, $visitTable) {
                if (!Schema::hasColumn($visitTable, $col)) {
                    $define($table);
                }
            };

            $add('reasonForVisit', fn ($t) => $t->string('reasonForVisit', 24)->nullable());
            $add('referredIn', fn ($t) => $t->string('referredIn', 3)->nullable());
            $add('referralNumber', fn ($t) => $t->string('referralNumber', 100)->nullable());
            $add('entryPoint', fn ($t) => $t->string('entryPoint', 16)->nullable());
            $add('entryPointOther', fn ($t) => $t->string('entryPointOther')->nullable());
        });

        // ── Risk profile ────────────────────────────────────────────────
        Schema::table($riskTable, function (Blueprint $table) use ($riskTable) {
            $add = function (string $col, callable $define) use ($table, $riskTable) {
                if (!Schema::hasColumn($riskTable, $col)) {
                    $define($table);
                }
            };

            $add('hivArtStatus', fn ($t) => $t->string('hivArtStatus', 16)->nullable());
            $add('hpvVaccination', fn ($t) => $t->string('hpvVaccination', 3)->nullable());
            $add('lastMenstrualPeriod', fn ($t) => $t->date('lastMenstrualPeriod')->nullable());
            $add('pregnancyStatus', fn ($t) => $t->string('pregnancyStatus', 16)->nullable());
            $add('menopausalStatus', fn ($t) => $t->string('menopausalStatus', 8)->nullable());

            // Symptoms identified
            foreach ([
                'symptomAbnormalBleeding', 'symptomPostCoitalBleeding', 'symptomPelvicPain',
                'symptomDischarge', 'symptomOther', 'symptomNone',
            ] as $flag) {
                $add($flag, fn ($t) => $t->boolean($flag)->default(false));
            }
            $add('symptomOtherDetail', fn ($t) => $t->string('symptomOtherDetail')->nullable());
            $add('symptomStatus', fn ($t) => $t->string('symptomStatus', 16)->nullable());

            // Cervical screening history
            $add('prevCervicalScreened', fn ($t) => $t->string('prevCervicalScreened', 3)->nullable());
            $add('prevCervicalScreeningDate', fn ($t) => $t->date('prevCervicalScreeningDate')->nullable());
            foreach ([
                'prevCervicalTestHpv', 'prevCervicalTestVia', 'prevCervicalTestPap',
                'prevCervicalTestColposcopy', 'prevCervicalTestOther',
            ] as $flag) {
                $add($flag, fn ($t) => $t->boolean($flag)->default(false));
            }
            $add('prevCervicalTestOtherDetail', fn ($t) => $t->string('prevCervicalTestOtherDetail')->nullable());
            $add('prevCervicalResult', fn ($t) => $t->string('prevCervicalResult', 24)->nullable());
            $add('prevCervicalTreated', fn ($t) => $t->string('prevCervicalTreated', 8)->nullable());
            $add('prevCervicalTreatmentType', fn ($t) => $t->string('prevCervicalTreatmentType', 24)->nullable());
        });
    }

    public function down(): void
    {
        $riskTable = $this->table(self::RISK_MODEL, 'risk_profiles');
        $visitTable = $this->table(self::VISIT_MODEL, 'screening_visits');

        $drop = function (string $table, array $cols) {
            $existing = array_values(array_filter($cols, fn ($c) => Schema::hasColumn($table, $c)));
            if ($existing) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($existing));
            }
        };

        $drop($visitTable, ['reasonForVisit', 'referredIn', 'referralNumber', 'entryPoint', 'entryPointOther']);

        $drop($riskTable, [
            'hivArtStatus', 'hpvVaccination', 'lastMenstrualPeriod', 'pregnancyStatus', 'menopausalStatus',
            'symptomAbnormalBleeding', 'symptomPostCoitalBleeding', 'symptomPelvicPain', 'symptomDischarge',
            'symptomOther', 'symptomNone', 'symptomOtherDetail', 'symptomStatus',
            'prevCervicalScreened', 'prevCervicalScreeningDate',
            'prevCervicalTestHpv', 'prevCervicalTestVia', 'prevCervicalTestPap',
            'prevCervicalTestColposcopy', 'prevCervicalTestOther', 'prevCervicalTestOtherDetail',
            'prevCervicalResult', 'prevCervicalTreated', 'prevCervicalTreatmentType',
        ]);
    }
};