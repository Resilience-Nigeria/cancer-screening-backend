<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * The existing isScreeningCenter/isTreatmentCenter booleans don't
     * distinguish secondary (confirmation/diagnostic workup) from
     * tertiary (full oncology treatment) facilities, which auto-referral
     * needs. Backfills a reasonable default from the existing flags so
     * nothing goes unclassified, but this should be reviewed/corrected
     * per facility by an admin.
     */
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->enum('facilityLevel', ['primary', 'secondary', 'tertiary'])->nullable()->after('facilityType');
        });

        DB::table('facilities')->where('isTreatmentCenter', true)->update(['facilityLevel' => 'tertiary']);
        DB::table('facilities')->where('isTreatmentCenter', false)->where('isScreeningCenter', true)->update(['facilityLevel' => 'primary']);
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn('facilityLevel');
        });
    }
};
