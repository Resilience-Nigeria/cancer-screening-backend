<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * The frontend wizard already has a "Book biopsy now" flow
     * (biopsyBookNow / biopsyBookingDate / biopsyBookingFacility /
     * biopsyBookingNotes) for scheduling a future or external biopsy
     * without an immediate result. None of those fields existed on
     * breast_screenings or in StoreBreastScreeningRequest, so the data
     * was silently dropped by $request->validated() on every submit.
     * This adds the missing columns. Separately, StoreBreastScreeningRequest
     * no longer requires an immediate biopsyResult whenever biopsyDone is
     * checked, since histology can take days/weeks to come back.
     */
    public function up(): void
    {
        Schema::table('breast_screenings', function (Blueprint $table) {
            $table->date('biopsyBookingDate')->nullable()->after('biopsyDone');
            $table->unsignedBigInteger('biopsyBookingFacilityId')->nullable()->after('biopsyBookingDate');
            $table->text('biopsyBookingNotes')->nullable()->after('biopsyBookingFacilityId');

            $table->foreign('biopsyBookingFacilityId')->references('facilityId')->on('facilities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('breast_screenings', function (Blueprint $table) {
            $table->dropForeign(['biopsyBookingFacilityId']);
            $table->dropColumn(['biopsyBookingDate', 'biopsyBookingFacilityId', 'biopsyBookingNotes']);
        });
    }
};
