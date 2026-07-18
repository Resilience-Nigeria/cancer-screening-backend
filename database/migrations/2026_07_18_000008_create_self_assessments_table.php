<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('self_assessments', function (Blueprint $table) {
            $table->id('assessmentId');
            $table->unsignedBigInteger('registrationId');
            $table->string('clientId')->nullable();

            $table->json('answersJson');

            $table->enum('riskCategory', ['low', 'average', 'increased', 'symptomatic_high']);
            $table->text('recommendation');
            $table->json('flaggedReasonsJson')->nullable();
            $table->json('suggestedCancerTypesJson')->nullable();

            $table->timestamp('completedAt')->nullable();
            $table->timestamps();

            $table->foreign('registrationId')->references('registrationId')->on('awareness_registrations')->cascadeOnDelete();
            $table->foreign('clientId')->references('clientId')->on('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('self_assessments');
    }
};
