<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('treatment_monitoring_logs', function (Blueprint $table) {
            $table->id('monitoringLogId');
            $table->unsignedBigInteger('treatmentPlanId');
            $table->date('logDate');

            $table->boolean('attended')->nullable();
            $table->boolean('missedAppointment')->nullable();
            $table->text('toxicity')->nullable();
            $table->json('labResults')->nullable();
            $table->text('imagingResponse')->nullable();
            $table->text('clinicalResponse')->nullable();
            $table->text('doseModification')->nullable();
            $table->boolean('treatmentInterruption')->nullable();
            $table->boolean('hospitalAdmission')->nullable();
            $table->boolean('emergencyVisit')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('recordedBy')->nullable();
            $table->timestamps();

            $table->foreign('treatmentPlanId')->references('treatmentPlanId')->on('treatment_plans')->cascadeOnDelete();
            $table->foreign('recordedBy')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_monitoring_logs');
    }
};
