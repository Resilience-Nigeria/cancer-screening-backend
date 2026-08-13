<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds everything the ablation/LEEP/colposcopy flow needs, written
     * directly against the current cervical_screenings schema (base create +
     * the moreThanOnePartner/ageAtFirstIntercourse/numberOfChildbirths/
     * contraceptiveUse migration). Does NOT touch `colposcopyDone` — that's
     * an existing, separate field ("was colposcopy actually performed") from
     * `colposcopyBookNow` ("book for colposcopy" intent) added here.
     *
     * Booking fields follow the same shape breast already uses for
     * biopsyBookNow / biopsyBookingDate / biopsyBookingFacilityId /
     * biopsyBookingNotes.
     */
    public function up(): void
    {
        Schema::table('cervical_screenings', function (Blueprint $table) {
            // Only relevant when hpvGenotype is 16, 18, 35, or 45 — or when
            // a colposcopy result below comes back positive.
            $table->enum('ablationEligibility', ['eligible', 'not_eligible', 'suspicious_invasion'])
                ->nullable()
                ->after('contraceptiveUse');

            // Eligible -> book ablative treatment
            $table->boolean('ablationBookNow')->default(false)->after('ablationEligibility');
            $table->date('ablationBookingDate')->nullable()->after('ablationBookNow');
            $table->unsignedBigInteger('ablationBookingFacilityId')->nullable()->after('ablationBookingDate');
            $table->text('ablationBookingNotes')->nullable()->after('ablationBookingFacilityId');

            // Not eligible -> book LEEP, then record histology
            $table->boolean('leepBookNow')->default(false)->after('ablationBookingNotes');
            $table->date('leepBookingDate')->nullable()->after('leepBookNow');
            $table->unsignedBigInteger('leepBookingFacilityId')->nullable()->after('leepBookingDate');
            $table->text('leepBookingNotes')->nullable()->after('leepBookingFacilityId');
            $table->enum('histologyResult', ['cin1', 'cin2', 'cin3', 'ais', 'cancer'])->nullable()->after('leepBookingNotes');

            // "Others" genotype -> book for colposcopy (distinct from the
            // existing colposcopyDone column)
            $table->boolean('colposcopyBookNow')->default(false)->after('histologyResult');
            $table->date('colposcopyBookingDate')->nullable()->after('colposcopyBookNow');
            $table->unsignedBigInteger('colposcopyBookingFacilityId')->nullable()->after('colposcopyBookingDate');
            $table->text('colposcopyBookingNotes')->nullable()->after('colposcopyBookingFacilityId');
            $table->enum('colposcopyResult', ['positive', 'negative'])->nullable()->after('colposcopyBookingNotes');

            // Derived outcome: suspicious-for-invasion, a negative VIA/
            // colposcopy triage, and a post-LEEP "Cancer" histology result
            // all route into this same shared value.
            $table->enum('referralPathway', ['ablation', 'leep', 'refer_further_evaluation'])
                ->nullable()
                ->after('colposcopyResult');
        });
    }

    public function down(): void
    {
        Schema::table('cervical_screenings', function (Blueprint $table) {
            $table->dropColumn([
                'ablationEligibility',
                'ablationBookNow',
                'ablationBookingDate',
                'ablationBookingFacilityId',
                'ablationBookingNotes',
                'leepBookNow',
                'leepBookingDate',
                'leepBookingFacilityId',
                'leepBookingNotes',
                'histologyResult',
                'colposcopyBookNow',
                'colposcopyBookingDate',
                'colposcopyBookingFacilityId',
                'colposcopyBookingNotes',
                'colposcopyResult',
                'referralPathway',
            ]);
        });
    }
};