<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('treatment_records', function (Blueprint $table) {
            $table->id('treatmentRecordId');
            $table->unsignedBigInteger('treatmentPlanId');

            $table->enum('modalityType', [
                'surgery', 'chemotherapy', 'radiotherapy', 'hormonal_therapy',
                'immunotherapy', 'targeted_therapy', 'palliative_care',
            ]);

            $table->date('startDate')->nullable();
            $table->date('completionDate')->nullable();
            $table->enum('completionStatus', ['ongoing', 'completed', 'discontinued'])->nullable();
            $table->string('reasonForDiscontinuation')->nullable();
            $table->text('notes')->nullable();

            // Modality-specific structured fields — e.g. for surgery:
            // {procedurePerformed, surgeon, hospital, surgicalMargins,
            // lymphNodeDissection, complications}; for chemotherapy:
            // {regimen, drugNames, cycleNumber, dose, frequency,
            // toxicity}; etc. Kept as JSON since the 7 modalities have
            // entirely different field sets.
            $table->json('modalityDetails')->nullable();

            $table->unsignedBigInteger('recordedBy')->nullable();
            $table->timestamps();

            $table->foreign('treatmentPlanId')->references('treatmentPlanId')->on('treatment_plans')->cascadeOnDelete();
            $table->foreign('recordedBy')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('treatment_records');
    }
};
