<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Community health workers sign up in the Bloom app as normal NCSR users with the NAVIGATOR role.
 * Their user row stays `inactive` until an admin checks who they are (bloom_navigator_signups).
 * Every Bloom booking becomes a case (bloom_cases) handled by a navigator at that facility.
 * No foreign keys, to match the rest of the Bloom tables (MyISAM-friendly).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('bloom_navigator_signups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('facility_id')->index();
            $table->string('status', 12)->default('pending')->index(); // pending | approved | rejected
            $table->string('applicant_note', 300)->nullable();         // e.g. position / staff ID, to help verification
            $table->string('review_note', 300)->nullable();            // shown to the applicant when rejected
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('bloom_cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('appointment_id')->unique();
            $table->unsignedBigInteger('account_id')->index();
            $table->unsignedBigInteger('facility_id')->index();
            $table->unsignedBigInteger('navigator_id')->nullable()->index(); // users.id
            $table->unsignedBigInteger('assigned_by')->nullable();           // users.id; null = assigned automatically
            $table->timestamp('assigned_at')->nullable();
            $table->string('step_community', 12)->default('not-started');
            $table->string('step_phc', 12)->default('not-started');
            $table->string('step_referral', 12)->default('not-started');
            $table->string('step_followup', 12)->default('not-started');
            $table->string('outcome', 20)->default('pending'); // pending | no-change | concerning-change
            $table->text('notes')->nullable();
            $table->string('referral_facility', 191)->nullable();
            $table->string('referral_date', 20)->nullable();
            $table->string('follow_up_date', 20)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloom_cases');
        Schema::dropIfExists('bloom_navigator_signups');
    }
};
