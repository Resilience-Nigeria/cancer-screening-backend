<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('prostate_screenings', function (Blueprint $table) {
            $table->id('screeningId');
            $table->unsignedBigInteger('clientId')->nullable();
            $table->foreignId('visitId')->nullable();
            $table->decimal('psaLevel', 10, 2)->nullable();
            $table->enum('screeningResult', ['negative', 'positive', 'suspicious']);
            $table->enum('dreResult', ['positive', 'negative'])->nullable();
            $table->integer('ipssScore')->nullable();
            $table->boolean('biopsyDone')->default(false);
            $table->string('gleasonScore')->nullable();
            $table->enum('treatmentReferral', ['referred', 'not_referred'])->nullable();
            $table->date('screeningDate')->nullable();
            $table->timestamps();

            $table->unique('visitId');
            $table->foreign('visitId')->references('visitId')->on('screening_visits')->nullOnDelete();
            $table->foreign('clientId')->references('clientId')->on('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prostate_screenings');
    }
};