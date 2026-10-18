<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * I-PSS: seven items scored 0–5, derived severity, and the single 0–6 quality-of-life item.
 * The existing ipssScore column keeps holding the 0–35 total.
 * Columns are added only if missing, so the migration is safe to re-run.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'ipssIncompleteEmptying',
        'ipssFrequency',
        'ipssIntermittency',
        'ipssUrgency',
        'ipssWeakStream',
        'ipssStraining',
        'ipssNocturia',
        'ipssQualityOfLife',
    ];

    public function up(): void
    {
        Schema::table('prostate_screenings', function (Blueprint $table) {
            foreach (self::COLUMNS as $col) {
                if (!Schema::hasColumn('prostate_screenings', $col)) {
                    $table->unsignedTinyInteger($col)->nullable();
                }
            }
            if (!Schema::hasColumn('prostate_screenings', 'ipssSeverity')) {
                $table->string('ipssSeverity', 16)->nullable();
            }
        });
    }

    public function down(): void
    {
        $cols = array_values(array_filter(
            array_merge(self::COLUMNS, ['ipssSeverity']),
            fn ($c) => Schema::hasColumn('prostate_screenings', $c)
        ));

        if ($cols) {
            Schema::table('prostate_screenings', fn (Blueprint $t) => $t->dropColumn($cols));
        }
    }
};