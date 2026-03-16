<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('case_outcomes', function (Blueprint $table) {
            $table->id('outcomeId');
            $table->unsignedBigInteger('clientId')->nullable();
            $table->boolean('cancerConfirmed')->default(false);
            $table->string('cancerType')->nullable();
            $table->string('stageAtDiagnosis')->nullable();
            $table->date('diagnosisDate')->nullable();
            $table->boolean('linkageToTreatment')->default(false);
            $table->string('treatmentFacility')->nullable();
            $table->date('treatmentInitiatedDate')->nullable();
            $table->boolean('treatmentCompleted')->default(false);
            $table->enum('treatmentOutcome', [
                'complete_remission',
                'partial_remission',
                'stable_disease',
                'progressive_disease',
            ])->nullable();
            $table->enum('followUpStatus', [
                'disease_free',
                'recurrence',
                'long_term_survival_with_chronic_disease',
                'treatment_related_complications',
            ])->nullable();
            $table->unsignedBigInteger('updatedBy')->nullable();
            $table->timestamps();

            $table->unique('clientId');
            $table->foreign('clientId')->references('clientId')->on('clients')->nullOnDelete();
            $table->foreign('updatedBy')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_outcomes');
    }
};