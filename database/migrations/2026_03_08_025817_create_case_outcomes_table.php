<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('case_outcomes', function (Blueprint $table) {
            $table->id('outcomeId');
            $table->string('clientId')->nullable();
            $table->string('cancerConfirmed')->nullable();
            $table->string('cancerType')->nullable();
            $table->string('stageAtDiagnosis')->nullable();
            $table->date('diagnosisDate')->nullable();
            $table->string('linkageToTreatment')->nullable();
            $table->string('treatmentFacility')->nullable();
            $table->date('treatmentInitiatedDate')->nullable();
            $table->string('treatmentCompleted')->nullable();
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


            $table->date('preScreeningCounselingDate')->nullable();
            $table->string('preScreeningCounselor')->nullable();
            $table->enum('preScreeningConsent', ['yes', 'no'])->nullable();
            
            $table->enum('screeningResult', ['negative', 'positive', 'inconclusive'])->nullable();
            $table->date('screeningDate')->nullable();
            
            $table->date('postScreeningCounselingDate')->nullable();
            $table->string('postScreeningCounselor')->nullable();
            
            $table->date('nextFollowUpDate')->nullable();
            $table->enum('followUpEstablished', ['yes', 'no'])->nullable();
            
            $table->string('diagnosis')->nullable();
            $table->string('cancerStage')->nullable();
            $table->text('stagingComments')->nullable();
            
            $table->enum('treatmentCommenced', ['yes', 'no'])->nullable();
            $table->date('treatmentCommencementDate')->nullable();
            $table->string('treatmentDelayReason')->nullable();
            
            $table->string('treatmentType')->nullable();
            
            $table->string('adherenceRating')->nullable();
            $table->integer('missedAppointments')->nullable();
            $table->json('missedAppointmentReasons')->nullable();
            $table->json('adherenceInterventions')->nullable();
            
            $table->string('treatmentStatus')->nullable();
            $table->date('treatmentCompletionDate')->nullable();
            $table->string('discontinuationReason')->nullable();
            $table->string('treatmentDuration')->nullable();
            
            $table->string('clinicalOutcome')->nullable();
            $table->date('outcomeAssessmentDate')->nullable();
            
            $table->text('remarks')->nullable();

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