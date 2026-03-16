<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('breast_screenings', function (Blueprint $table) {
            $table->id('screeningId');
            $table->unsignedBigInteger('visitId')->nullable();
            $table->enum('method', ['cbe', 'mammography', 'uss']);
            $table->date('screeningDate');
            $table->string('biradsScore')->nullable();
            $table->string('breastDensity')->nullable();
            $table->boolean('biopsyDone')->default(false);
            $table->enum('histologyResult', ['negative', 'positive'])->nullable();
            $table->enum('treatmentReferral', ['referred', 'not_referred'])->nullable();
            $table->timestamps();

            $table->unique('visitId');
            $table->foreign('visitId')->references('visitId')->on('screening_visits')->nullOnDelete();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('breast_screenings');
    }
};