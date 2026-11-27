<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Changes from the platform simulation report:
 *  - breast CBE: result per breast, physical changes list, biopsy procedure, recommended next imaging;
 *  - "Not sure / can't recall" for radiation exposure (column widened to a short string);
 *  - "Biopsy/FNE" service renamed to "Biopsy".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('breast_screenings', function (Blueprint $t) {
            if (!Schema::hasColumn('breast_screenings', 'leftCbeResult')) $t->string('leftCbeResult', 30)->nullable();
            if (!Schema::hasColumn('breast_screenings', 'rightCbeResult')) $t->string('rightCbeResult', 30)->nullable();
            if (!Schema::hasColumn('breast_screenings', 'physicalChanges')) $t->json('physicalChanges')->nullable();
            if (!Schema::hasColumn('breast_screenings', 'physicalChangesOther')) $t->string('physicalChangesOther', 255)->nullable();
            if (!Schema::hasColumn('breast_screenings', 'biopsyProcedure')) $t->string('biopsyProcedure', 40)->nullable();
            if (!Schema::hasColumn('breast_screenings', 'biopsyProcedureOther')) $t->string('biopsyProcedureOther', 255)->nullable();
            if (!Schema::hasColumn('breast_screenings', 'recommendedNextStep')) $t->string('recommendedNextStep', 40)->nullable();
        });

        // Radiation exposure: allow "unknown" (Not sure / can't recall), whatever the column type was.
        if (Schema::hasColumn('client_risk_profiles', 'ionizingRadiationExposure')) {
            Schema::table('client_risk_profiles', function (Blueprint $t) {
                $t->string('ionizingRadiationExposure', 20)->nullable()->change();
            });
        }

        // "Biopsy/FNE" (or "Biopsy / FNA") → "Biopsy"
        if (Schema::hasTable('services')) {
            DB::table('services')
                ->where(fn ($q) => $q->where('name', 'like', '%FNE%')->orWhere('name', 'like', 'Biopsy%/%FN%'))
                ->update(['name' => 'Biopsy']);
        }
    }

    public function down(): void
    {
        Schema::table('breast_screenings', function (Blueprint $t) {
            foreach (['leftCbeResult', 'rightCbeResult', 'physicalChanges', 'physicalChangesOther', 'biopsyProcedure', 'biopsyProcedureOther', 'recommendedNextStep'] as $c) {
                if (Schema::hasColumn('breast_screenings', $c)) $t->dropColumn($c);
            }
        });
    }
};
