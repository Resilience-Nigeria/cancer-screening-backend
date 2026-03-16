<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cervical_screenings', function (Blueprint $table) {
            $table->id('screeningId');
            $table->unsignedBigInteger('visitId')->nullable();
            $table->enum('method', ['via', 'pap', 'hpv']);
            $table->date('screeningDate');
            $table->enum('result', ['negative', 'positive', 'suspicious']);
            $table->string('hpvResult')->nullable();
            $table->string('hpvGenotype')->nullable();
            $table->boolean('colposcopyDone')->default(false);
            $table->boolean('biopsyDone')->default(false);
            $table->enum('biopsyResult', ['positive', 'negative'])->nullable();
            $table->boolean('treatmentProvided')->default(false);
            $table->boolean('referralCompleted')->default(false);
            $table->enum('treatmentReferral', ['referred', 'not_referred'])->nullable();

            $table->timestamps();

            $table->unique('visitId');
            $table->foreign('visitId')->references('visitId')->on('screening_visits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cervical_screenings');
    }
};