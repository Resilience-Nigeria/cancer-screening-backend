<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * hivStatus/hbvStatus/hcvStatus and familyHistory already exist on this
     * table. This adds the remaining Stage 2 Section C confirm-checklist
     * items (previous cancer, previous surgeries, diabetes, hypertension,
     * previous screening) as structured fields rather than folding them
     * into the free-text comorbiditiesJson list, since a clinician
     * "confirming" a specific item needs a real yes/no/unknown, not a
     * string match against free text.
     */
    public function up(): void
    {
        Schema::table('client_risk_profiles', function (Blueprint $table) {
            $table->enum('previousCancer', ['yes', 'no', 'unknown'])->nullable();
            $table->string('previousCancerDetails')->nullable();

            $table->enum('previousSurgeries', ['yes', 'no', 'unknown'])->nullable();
            $table->string('previousSurgeriesDetails')->nullable();

            $table->enum('diabetes', ['yes', 'no', 'unknown'])->nullable();
            $table->enum('hypertension', ['yes', 'no', 'unknown'])->nullable();

            $table->enum('previousScreening', ['yes', 'no', 'unknown'])->nullable();
            $table->string('previousScreeningDetails')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('client_risk_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'previousCancer', 'previousCancerDetails',
                'previousSurgeries', 'previousSurgeriesDetails',
                'diabetes', 'hypertension',
                'previousScreening', 'previousScreeningDetails',
            ]);
        });
    }
};
