<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('follow_up_schedules', function (Blueprint $table) {
            $table->id('scheduleId');
            $table->unsignedBigInteger('treatmentPlanId');
            $table->date('dueDate');
            $table->text('activities')->nullable();
            $table->enum('status', ['pending', 'completed', 'missed'])->default('pending');
            $table->timestamp('reminderSentAt')->nullable();
            $table->timestamp('escalationSentAt')->nullable();
            $table->date('completedDate')->nullable();
            $table->text('completionNotes')->nullable();
            $table->timestamps();

            $table->foreign('treatmentPlanId')->references('treatmentPlanId')->on('treatment_plans')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_schedules');
    }
};
