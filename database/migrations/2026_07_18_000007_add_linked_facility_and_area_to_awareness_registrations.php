<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * AwarenessRegistrationController and StoreAwarenessRegistrationRequest
     * already reference linkedFacilityId and areaOfResidence, but neither
     * column ever existed on this table — both were being silently dropped
     * (linkedFacilityId also wasn't fillable, compounding the problem).
     * This adds them and wires up the facility() relationship the
     * OtpController::verify response depends on.
     */
    public function up(): void
    {
        Schema::table('awareness_registrations', function (Blueprint $table) {
            if (!Schema::hasColumn('awareness_registrations', 'areaOfResidence')) {
                $table->string('areaOfResidence')->nullable()->after('lgaOfResidence');
            }
            if (!Schema::hasColumn('awareness_registrations', 'linkedFacilityId')) {
                $table->unsignedBigInteger('linkedFacilityId')->nullable()->after('status');
                $table->foreign('linkedFacilityId')->references('facilityId')->on('facilities')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('awareness_registrations', function (Blueprint $table) {
            if (Schema::hasColumn('awareness_registrations', 'linkedFacilityId')) {
                $table->dropForeign(['linkedFacilityId']);
                $table->dropColumn('linkedFacilityId');
            }
            if (Schema::hasColumn('awareness_registrations', 'areaOfResidence')) {
                $table->dropColumn('areaOfResidence');
            }
        });
    }
};
