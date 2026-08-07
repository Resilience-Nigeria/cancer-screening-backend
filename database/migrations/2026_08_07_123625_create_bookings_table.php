<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
    $table->id('bookingId');

    $table->string('clientId');
    $table->foreign('clientId')->references('clientId')->on('clients')->cascadeOnDelete();

    $table->unsignedBigInteger('visitId')->nullable();
    // FK to your visits table — same caveat as before, I don't have its real name/PK confirmed

    $table->unsignedBigInteger('serviceId');
    $table->foreign('serviceId')->references('serviceId')->on('services')->cascadeOnDelete();

    $table->unsignedBigInteger('facilityId');
    $table->foreign('facilityId')->references('facilityId')->on('facilities')->cascadeOnDelete();

    // Context only — e.g. which cancer type this booking relates to.
    // Not a capability filter (facilityServices handles that); just useful
    // for reporting/display ("Biopsy — Breast" vs "Biopsy — Cervical").
    $table->string('cancerType')->nullable();

    $table->date('bookingDate');
    $table->text('notes')->nullable();
    $table->enum('status', ['pending', 'completed', 'cancelled'])->default('pending');

    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
