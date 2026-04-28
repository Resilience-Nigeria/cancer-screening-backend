<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('liver_screenings', function (Blueprint $table) {
            $table->id('screeningId');
            $table->unsignedBigInteger('clientId')->nullable();
            $table->unsignedBigInteger('visitId')->nullable();
            $table->enum('method', ['uss', 'afp']);
            $table->date('screeningDate')->nullable();
            $table->enum('screeningResult', ['negative', 'positive', 'suspicious']);
            $table->enum('hbvStatus', ['positive', 'negative']);
            $table->enum('hcvStatus', ['positive', 'negative']);
            $table->decimal('afpValue', 10, 2)->nullable();
            $table->boolean('lesionDetected')->default(false);
            $table->enum('treatmentReferral', ['referred', 'not_referred'])->nullable();
            $table->boolean('treatmentProvided')->default(false);
            $table->timestamps();

            $table->unique('visitId');
            $table->foreign('visitId')->references('visitId')->on('screening_visits')->nullOnDelete();
            $table->foreign('clientId')->references('clientId')->on('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liver_screenings');
    }
};