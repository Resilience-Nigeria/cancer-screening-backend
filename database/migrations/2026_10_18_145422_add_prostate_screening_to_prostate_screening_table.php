<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prostate Step 5/6: derived PSA category and recall interval, plus the two bookings
 * (next screening / repeat PSA, and urology appointment).
 * Columns are added only if missing, so the migration is safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prostate_screenings', function (Blueprint $table) {
            $add = function (string $col, callable $define) use ($table) {
                if (!Schema::hasColumn('prostate_screenings', $col)) {
                    $define($table);
                }
            };

            $add('psaCategory', fn ($t) => $t->string('psaCategory', 80)->nullable());
            $add('recallInterval', fn ($t) => $t->string('recallInterval', 60)->nullable());

            // Next screening / repeat PSA booking
            $add('recallBookNow', fn ($t) => $t->boolean('recallBookNow')->default(false));
            $add('recallBookingDate', fn ($t) => $t->date('recallBookingDate')->nullable());
            $add('recallBookingFacilityId', fn ($t) => $t->unsignedBigInteger('recallBookingFacilityId')->nullable());
            $add('recallBookingNotes', fn ($t) => $t->text('recallBookingNotes')->nullable());

            // Urology appointment booking
            $add('urologyBookNow', fn ($t) => $t->boolean('urologyBookNow')->default(false));
            $add('urologyBookingDate', fn ($t) => $t->date('urologyBookingDate')->nullable());
            $add('urologyBookingFacilityId', fn ($t) => $t->unsignedBigInteger('urologyBookingFacilityId')->nullable());
            $add('urologyBookingNotes', fn ($t) => $t->text('urologyBookingNotes')->nullable());
        });
    }

    public function down(): void
    {
        $cols = array_values(array_filter([
            'psaCategory', 'recallInterval',
            'recallBookNow', 'recallBookingDate', 'recallBookingFacilityId', 'recallBookingNotes',
            'urologyBookNow', 'urologyBookingDate', 'urologyBookingFacilityId', 'urologyBookingNotes',
        ], fn ($c) => Schema::hasColumn('prostate_screenings', $c)));

        if ($cols) {
            Schema::table('prostate_screenings', fn (Blueprint $t) => $t->dropColumn($cols));
        }
    }
};