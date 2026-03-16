<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('colorectal_screenings', function (Blueprint $table) {
            $table->id('screeningId');
            $table->unsignedBigInteger('visitId')->nullable();
            $table->enum('method', ['fit', 'fobt', 'colonoscopy']);
            $table->date('screeningDate');
            $table->enum('result', ['negative', 'positive', 'suspicious']);
            $table->boolean('polypDetected')->default(false);
            $table->enum('histologyResult', ['negative', 'positive'])->nullable();
            $table->enum('treatmentReferral', ['referred', 'not_referred'])->nullable();
            $table->boolean('treatmentProvided')->default(false);
            $table->timestamps();

            $table->unique('visitId');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('colorectal_screenings');
    }
};