<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Stage 3 — Diagnostic Evaluation. Confirms or excludes cancer via
     * specialist review, advanced examination, targeted diagnostic
     * tests (varies by suspected cancer type), and definitive
     * histopathology.
     *
     * diagnosticTests and bloodInvestigations are JSON rather than a
     * column per possible test, since the test battery varies widely by
     * cancer type (mammography/MRI/core-needle-biopsy for breast vs.
     * colposcopy/biopsy for cervix vs. PSA/MRI/biopsy for prostate,
     * etc.) — a rigid column-per-test schema would mean a migration
     * every time a facility's protocol changes.
     */
    public function up(): void
    {
        Schema::create('diagnostic_evaluations', function (Blueprint $table) {
            $table->id('evaluationId');
            $table->string('clientId')->nullable();
            $table->unsignedBigInteger('facilityId');
            $table->unsignedBigInteger('referralId')->nullable();
            $table->date('evaluationDate');

            $table->enum('suspectedCancerType', [
                'breast', 'cervical', 'prostate', 'colorectal', 'lung', 'liver', 'oral',
            ]);

            // A. Specialist Consultation — mostly a review of existing
            // data (risk profile, prior screening findings), plus the
            // specialist's own notes/impression.
            $table->text('consultationNotes')->nullable();
            $table->unsignedBigInteger('consultedBy')->nullable();
            $table->timestamp('consultedAt')->nullable();

            // B. Advanced Examination
            $table->text('advancedExaminationFindings')->nullable();

            // C. Diagnostic Tests — structure varies by cancer type, so
            // this is a JSON map of {testName: {done, date, result}}.
            $table->json('diagnosticTests')->nullable();

            // Blood investigations (CBC, LFT, RFT, tumour markers) —
            // done "where indicated", so also flexible JSON.
            $table->json('bloodInvestigations')->nullable();

            // D. Pathology — the definitive diagnosis.
            $table->enum('histopathologyResult', [
                'benign', 'pre_cancer', 'malignant', 'inconclusive',
            ])->nullable();
            $table->text('pathologyNotes')->nullable();
            $table->date('pathologyDate')->nullable();

            $table->enum('status', ['in_progress', 'completed'])->default('in_progress');
            $table->unsignedBigInteger('completedBy')->nullable();
            $table->timestamp('completedAt')->nullable();

            $table->timestamps();

            $table->foreign('clientId')->references('clientId')->on('clients')->nullOnDelete();
            $table->foreign('facilityId')->references('facilityId')->on('facilities')->nullOnDelete();
            $table->foreign('referralId')->references('referralId')->on('client_referrals')->nullOnDelete();
            $table->foreign('consultedBy')->references('id')->on('users')->nullOnDelete();
            $table->foreign('completedBy')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diagnostic_evaluations');
    }
};
