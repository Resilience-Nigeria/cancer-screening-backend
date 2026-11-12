<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screening_samples', function (Blueprint $table) {
            $table->timestamp('deletedAt')->nullable()->index();
            $table->unsignedBigInteger('deletedById')->nullable();
            $table->string('deleteReason', 500)->nullable();
        });

        Schema::create('screening_sample_deletion_requests', function (Blueprint $table) {
            $table->id('requestId');
            $table->unsignedBigInteger('sampleId')->index();
            $table->string('clientId')->index();
            $table->unsignedBigInteger('facilityId')->nullable()->index();
            $table->unsignedBigInteger('requestedById');
            $table->text('reason');
            $table->string('status', 20)->default('pending')->index(); // pending | approved | rejected
            $table->unsignedBigInteger('decidedById')->nullable();
            $table->timestamp('decidedAt')->nullable();
            $table->string('decisionNote', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screening_sample_deletion_requests');
        Schema::table('screening_samples', function (Blueprint $table) {
            $table->dropColumn(['deletedAt', 'deletedById', 'deleteReason']);
        });
    }
};
