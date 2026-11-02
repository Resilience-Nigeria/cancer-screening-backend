<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hpv_samples', function (Blueprint $table) {
            $table->id('hpvSampleId');
            $table->string('clientId')->index();
            $table->unsignedBigInteger('facilityId')->nullable()->index();

            // Collection
            $table->date('sampleDate');
            $table->string('collectionMethod', 30); // clinician_collected | self_collected
            $table->string('labSampleNumber', 100)->nullable();
            $table->string('collectedBy')->nullable();
            $table->unsignedBigInteger('collectedById')->nullable();
            $table->text('notes')->nullable();

            // Result
            $table->string('status', 20)->default('collected')->index(); // collected | result_released
            $table->string('result', 20)->nullable();                    // positive | negative | indeterminate
            $table->string('hpvGenotype', 10)->nullable();
            $table->date('resultDate')->nullable();
            $table->text('resultNotes')->nullable();
            $table->unsignedBigInteger('resultEnteredById')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hpv_samples');
    }
};
