<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * 4.2 Final Clinical Decision, moved here from Stage 4's
     * treatment_plans table. Stage 3's pathology result (benign/
     * pre_cancer/malignant/inconclusive) already determines this
     * classification, so re-deciding it in a separate stage was
     * redundant — it now happens right after pathology instead.
     */
    public function up(): void
    {
        Schema::table('diagnostic_evaluations', function (Blueprint $table) {
            $table->enum('decisionPathway', ['no_cancer', 'pre_cancerous', 'cancer_confirmed'])->nullable()->after('pathologyDate');
            $table->text('managementNotes')->nullable();
            $table->date('routineRecallDate')->nullable();
            $table->string('procedurePerformed')->nullable();
            $table->text('procedureComplications')->nullable();
            $table->text('surveillanceNotes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('diagnostic_evaluations', function (Blueprint $table) {
            $table->dropColumn([
                'decisionPathway', 'managementNotes', 'routineRecallDate',
                'procedurePerformed', 'procedureComplications', 'surveillanceNotes',
            ]);
        });
    }
};
