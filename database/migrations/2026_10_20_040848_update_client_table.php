<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table behind App\Models\ClientRiskProfile. Laravel's default for that model name is
     * "client_risk_profiles"; if the model sets $table to something else, change it here.
     */
    private string $riskTable = 'client_risk_profiles';

    public function up(): void
    {
        // ── clients: religion + marital status ───────────────────────────────
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'religion')) {
                $table->string('religion', 30)->nullable()->after('occupation');
            }
            if (!Schema::hasColumn('clients', 'religionOther')) {
                $table->string('religionOther', 100)->nullable()->after('religion');
            }
            if (!Schema::hasColumn('clients', 'maritalStatus')) {
                $table->string('maritalStatus', 20)->nullable()->after('religionOther');
            }
        });

        // ── risk profile: sexual & reproductive history + breastfeeding ──────
        Schema::table($this->riskTable, function (Blueprint $table) {
            $add = [
                'previousMarriage'               => fn () => $table->string('previousMarriage', 5)->nullable(),           // yes | no
                'numberOfPreviousMarriages'      => fn () => $table->unsignedSmallInteger('numberOfPreviousMarriages')->nullable(),
                'numberOfSexualPartners'         => fn () => $table->unsignedSmallInteger('numberOfSexualPartners')->nullable(),
                'moreThanOnePartner'             => fn () => $table->string('moreThanOnePartner', 5)->nullable(),         // yes | no
                'ageAtFirstIntercourse'          => fn () => $table->unsignedSmallInteger('ageAtFirstIntercourse')->nullable(),
                'numberOfChildbirths'            => fn () => $table->unsignedSmallInteger('numberOfChildbirths')->nullable(),
                'contraceptiveUse'               => fn () => $table->string('contraceptiveUse', 30)->nullable(),
                'avgBreastfeedingMonthsPerChild' => fn () => $table->decimal('avgBreastfeedingMonthsPerChild', 4, 1)->nullable(),
            ];

            foreach ($add as $column => $define) {
                if (!Schema::hasColumn($this->riskTable, $column)) {
                    $define();
                }
            }
        });

        // Cumulative breastfeeding = children x average months, so it can be fractional (e.g. 7.5).
        // ->change() is native on Laravel 10+. On older versions install doctrine/dbal first.
        if (Schema::hasColumn($this->riskTable, 'breastfeedingDuration')) {
            // MySQL rebuilds the table for a type change and re-validates every foreign key while
            // doing so. Orphaned rows (clientId pointing at a deleted client) make that fail with
            // error 1452, so FK checks are switched off for just this statement. Clean up the
            // orphans separately (see the query in the deployment notes).
            Schema::disableForeignKeyConstraints();
            try {
                Schema::table($this->riskTable, function (Blueprint $table) {
                    $table->decimal('breastfeedingDuration', 5, 1)->nullable()->change();
                });
            } finally {
                Schema::enableForeignKeyConstraints();
            }
        } else {
            Schema::table($this->riskTable, function (Blueprint $table) {
                $table->decimal('breastfeedingDuration', 5, 1)->nullable();
            });
        }

        // Optional back-fill (uncomment to use): copy the four moved fields from each client's
        // most recent cervical screening into their latest risk profile where still empty.
        //
        // DB::statement("
        //     UPDATE {$this->riskTable} rp
        //     JOIN (
        //         SELECT cs.clientId, cs.moreThanOnePartner, cs.ageAtFirstIntercourse,
        //                cs.numberOfChildbirths, cs.contraceptiveUse
        //         FROM cervical_screenings cs
        //         JOIN (SELECT clientId, MAX(screeningId) AS maxId FROM cervical_screenings GROUP BY clientId) m
        //           ON m.maxId = cs.screeningId
        //     ) c ON c.clientId = rp.clientId
        //     SET rp.moreThanOnePartner    = COALESCE(rp.moreThanOnePartner,    c.moreThanOnePartner),
        //         rp.ageAtFirstIntercourse = COALESCE(rp.ageAtFirstIntercourse, c.ageAtFirstIntercourse),
        //         rp.numberOfChildbirths   = COALESCE(rp.numberOfChildbirths,   c.numberOfChildbirths),
        //         rp.contraceptiveUse      = COALESCE(rp.contraceptiveUse,      c.contraceptiveUse)
        // ");
    }

    public function down(): void
    {
        Schema::table($this->riskTable, function (Blueprint $table) {
            $drop = array_filter([
                'previousMarriage', 'numberOfPreviousMarriages', 'numberOfSexualPartners',
                'moreThanOnePartner', 'ageAtFirstIntercourse', 'numberOfChildbirths',
                'contraceptiveUse', 'avgBreastfeedingMonthsPerChild',
            ], fn ($c) => Schema::hasColumn($this->riskTable, $c));

            if ($drop) {
                $table->dropColumn($drop);
            }
        });

        Schema::table($this->riskTable, function (Blueprint $table) {
            $table->unsignedSmallInteger('breastfeedingDuration')->nullable()->change();
        });

        Schema::table('clients', function (Blueprint $table) {
            $drop = array_filter(['religion', 'religionOther', 'maritalStatus'], fn ($c) => Schema::hasColumn('clients', $c));
            if ($drop) {
                $table->dropColumn($drop);
            }
        });
    }
};