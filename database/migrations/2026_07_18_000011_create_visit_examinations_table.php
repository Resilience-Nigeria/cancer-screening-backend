<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('visit_examinations', function (Blueprint $table) {
            $table->id('examinationId');
            $table->unsignedBigInteger('visitId');

            // Basic observations
            $table->decimal('heightCm', 8, 2)->nullable();
            $table->decimal('weightKg', 8, 2)->nullable();
            $table->decimal('bmi', 8, 2)->nullable();
            $table->unsignedSmallInteger('bloodPressureSystolic')->nullable();
            $table->unsignedSmallInteger('bloodPressureDiastolic')->nullable();
            $table->unsignedSmallInteger('pulse')->nullable();
            $table->decimal('temperatureCelsius', 4, 1)->nullable();

            // General examination
            $table->boolean('pallor')->default(false);
            $table->boolean('weightLossNoted')->default(false);
            $table->boolean('enlargedLymphNodes')->default(false);
            $table->string('enlargedLymphNodesSite')->nullable();
            $table->boolean('jaundice')->default(false);

            $table->text('notes')->nullable();

            $table->unsignedBigInteger('examinedBy')->nullable();
            $table->timestamp('examinedAt')->nullable();
            $table->timestamps();

            $table->unique('visitId');
            $table->foreign('visitId')->references('visitId')->on('screening_visits')->cascadeOnDelete();
            $table->foreign('examinedBy')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_examinations');
    }
};
