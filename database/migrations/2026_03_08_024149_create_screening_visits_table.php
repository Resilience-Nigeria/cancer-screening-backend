<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('screening_visits', function (Blueprint $table) {
            $table->id('visitId');
            $table->string('clientId')->nullable();
            $table->unsignedBigInteger('facilityId')->nullable();
            $table->date('visitDate');
            $table->enum('visitType', ['initial', 'follow_up'])->default('initial');
            $table->text('notes')->nullable();
            $table->boolean('treatmentReferral')->nullable();
            $table->unsignedBigInteger('createdBy')->nullable();
            $table->timestamps();
            $table->string('remarks')->nullable();

            $table->foreign('clientId')->references('clientId')->on('clients')->nullOnDelete();
            $table->foreign('facilityId')->references('facilityId')->on('facilities')->nullOnDelete();
            $table->foreign('createdBy')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screening_visits');
    }
};