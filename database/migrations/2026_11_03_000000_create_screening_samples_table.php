<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screening_samples', function (Blueprint $table) {
            $table->id('sampleId');
            $table->string('clientId')->index();
            $table->unsignedBigInteger('visitId')->nullable()->index();
            $table->unsignedBigInteger('facilityId')->nullable()->index();

            // Which test
            $table->string('cancerType', 20)->index();   // breast | cervical | prostate | liver | colorectal
            $table->string('testType', 40)->index();     // key from resources/screening_tests.json

            // Collection (sample taken, or procedure performed)
            $table->string('specimenType', 100)->nullable();
            $table->string('collectionMethod', 30)->nullable();
            $table->date('sampleDate');
            $table->string('labSampleNumber', 100)->nullable();
            $table->string('collectedBy')->nullable();
            $table->unsignedBigInteger('collectedById')->nullable();
            $table->text('notes')->nullable();

            // Result
            $table->string('status', 20)->default('collected')->index(); // collected | result_released
            $table->string('result', 40)->nullable();
            $table->string('resultSeverity', 10)->nullable();            // normal | abnormal | urgent | neutral
            $table->decimal('resultValue', 12, 2)->nullable();           // PSA, AFP
            $table->string('resultDetail', 40)->nullable();              // HPV genotype, ISUP grade group
            $table->date('resultDate')->nullable();
            $table->text('resultNotes')->nullable();
            $table->unsignedBigInteger('resultEnteredById')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screening_samples');
    }
};
