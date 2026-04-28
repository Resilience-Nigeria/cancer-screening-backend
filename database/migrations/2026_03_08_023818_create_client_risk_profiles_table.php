<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_risk_profiles', function (Blueprint $table) {
            $table->id('riskProfileId');
            $table->unsignedBigInteger('clientId')->nullable();
            $table->string('familyHistory')->nullable();
            $table->enum('smokingStatus', ['never', 'active_smoker', 'former_smoker', 'passive_smoker'])->nullable();
            $table->enum('alcoholConsumption', ['none', 'occasional', 'regular'])->nullable();
            $table->decimal('weightKg', 8, 2)->nullable();
            $table->decimal('heightCm', 8, 2)->nullable();
            $table->decimal('bmi', 8, 2)->nullable();
            $table->enum('hivStatus', ['positive', 'negative', 'unknown'])->nullable();
            $table->enum('hbvStatus', ['positive', 'negative', 'unknown'])->nullable();
            $table->enum('hcvStatus', ['positive', 'negative', 'unknown'])->nullable();
            $table->json('comorbiditiesJson')->nullable();
            $table->timestamp('recordedAt')->nullable();
            $table->unsignedBigInteger('recordedBy')->nullable();
            $table->timestamps();

            $table->foreign('clientId')->references('clientId')->on('clients')->nullOnDelete();
            $table->foreign('recordedBy')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_risk_profiles');
    }
};