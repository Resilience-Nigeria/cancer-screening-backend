<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
 // database/migrations/xxxx_create_client_referrals_table.php
public function up(): void
{
    Schema::create('clientReferrals', function (Blueprint $table) {
        $table->id('referralId');
        $table->string('clientId');
        $table->foreign('clientId')->references('clientId')->on('clients')->cascadeOnDelete();

        $table->unsignedBigInteger('fromFacilityId')->nullable();
        $table->foreign('fromFacilityId')->references('facilityId')->on('facilities')->nullOnDelete();

        $table->unsignedBigInteger('toFacilityId');
        $table->foreign('toFacilityId')->references('facilityId')->on('facilities')->cascadeOnDelete();

        $table->enum('referralType', [
            'awareness_to_screening',
            'screening_to_confirmation',
            'confirmation_to_treatment',
        ]);
        $table->enum('status', ['pending', 'accepted', 'completed', 'declined'])
              ->default('pending');

        $table->date('referralDate');
        $table->text('notes')->nullable();
        $table->timestamp('notifiedAt')->nullable();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('clientReferrals');
}
};
