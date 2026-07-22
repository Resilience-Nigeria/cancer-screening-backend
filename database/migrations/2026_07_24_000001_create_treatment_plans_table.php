<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Stage 4 — Treatment & Care Management. One row per confirmed
     * diagnosis episode (linked back to the Stage 3 diagnostic
     * evaluation that established it). Treatment modalities themselves
     * (Surgery, Chemo, etc.) live in a separate treatment_records table
     * since a patient may have several; monitoring and follow-up
     * schedule live in their own tables too, all pointing back here.
     */
    public function up(): void
    {
        Schema::create('treatment_plans', function (Blueprint $table) {
            $table->id('treatmentPlanId');
            $table->string('clientId')->nullable();
            $table->unsignedBigInteger('evaluationId')->nullable();
            $table->unsignedBigInteger('facilityId')->nullable();

            // 4.1 Review of Diagnostic Findings
            $table->enum('performanceStatusScale', ['ecog', 'karnofsky'])->nullable();
            $table->string('performanceStatusValue')->nullable();
            $table->text('comorbidities')->nullable();
            $table->text('patientPreferencesNotes')->nullable();
            $table->boolean('consentObtained')->nullable();
            $table->date('consentDate')->nullable();

            // 4.2 Final Clinical Decision
            $table->enum('decisionPathway', ['no_cancer', 'pre_cancerous', 'cancer_confirmed'])->nullable();
            $table->text('managementNotes')->nullable();
            // Pathway A specifics
            $table->date('routineRecallDate')->nullable();
            // Pathway B specifics
            $table->string('procedurePerformed')->nullable();
            $table->text('procedureComplications')->nullable();
            $table->text('surveillanceNotes')->nullable();

            // 4.3 Cancer Staging
            $table->string('tStage')->nullable();
            $table->string('nStage')->nullable();
            $table->string('mStage')->nullable();
            $table->enum('clinicalStage', ['I', 'II', 'III', 'IV'])->nullable();
            $table->string('histologicalType')->nullable();
            $table->string('tumourGrade')->nullable();
            $table->json('biomarkers')->nullable();

            // 4.4 MDT Review
            $table->json('mdtParticipants')->nullable();
            $table->date('mdtDate')->nullable();
            $table->text('mdtDecisionNotes')->nullable();
            $table->boolean('clinicalTrialEligible')->nullable();

            // 4.5 Treatment Planning
            $table->enum('treatmentIntent', [
                'curative', 'neoadjuvant', 'adjuvant', 'disease_control', 'palliative',
            ])->nullable();

            // 4.8 Treatment Outcome
            $table->enum('treatmentOutcome', [
                'complete_response', 'partial_response', 'stable_disease',
                'progressive_disease', 'recurrence', 'remission', 'disease_free',
                'deceased', 'lost_to_followup',
            ])->nullable();
            $table->date('outcomeDate')->nullable();
            $table->text('outcomeNotes')->nullable();

            // 4.9 Survivorship Care Plan
            $table->json('survivorshipPlan')->nullable();

            $table->enum('status', ['active', 'closed'])->default('active');
            $table->unsignedBigInteger('createdBy')->nullable();
            $table->timestamps();

            $table->foreign('clientId')->references('clientId')->on('clients')->nullOnDelete();
            $table->foreign('evaluationId')->references('evaluationId')->on('diagnostic_evaluations')->nullOnDelete();
            $table->foreign('facilityId')->references('facilityId')->on('facilities')->nullOnDelete();
            $table->foreign('createdBy')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_plans');
    }
};
