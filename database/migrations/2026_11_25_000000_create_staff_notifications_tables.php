<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications for staff (NCSR Mobile App bell + phone push):
 *  staff_push_tokens    one row per phone (Expo push token) a staff member signed in on
 *  staff_broadcasts     messages sent from the web dashboard (who sent what to whom)
 *  staff_notifications  one row per person per message — the in-app bell list
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('staff_push_tokens')) {
            Schema::create('staff_push_tokens', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('userId')->index();
                $table->string('token', 255)->unique();
                $table->string('platform', 20)->nullable();      // android | ios
                $table->string('deviceName', 120)->nullable();
                $table->string('appVersion', 30)->nullable();
                $table->boolean('active')->default(true)->index();
                $table->timestamp('lastSeenAt')->nullable();
                $table->string('lastError', 255)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('staff_broadcasts')) {
            Schema::create('staff_broadcasts', function (Blueprint $table) {
                $table->id();
                $table->string('title', 120);
                $table->text('body');
                $table->string('route', 120)->nullable();         // screen to open in the app when tapped
                $table->json('audience')->nullable();             // {roles:[], facilityIds:[], userIds:[]}
                $table->string('audienceText', 255)->nullable();  // "Nurses at FMC Jabi"
                $table->boolean('push')->default(true);
                $table->unsignedInteger('recipients')->default(0);
                $table->unsignedInteger('phones')->default(0);
                $table->unsignedBigInteger('createdById')->nullable()->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('staff_notifications')) {
            Schema::create('staff_notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('userId')->index();
                $table->string('type', 40)->default('message');  // message | referral | deletion_request | deletion_decision | ...
                $table->string('title', 120);
                $table->text('body')->nullable();
                $table->string('route', 120)->nullable();
                $table->json('data')->nullable();
                $table->unsignedBigInteger('broadcastId')->nullable()->index();
                $table->timestamp('readAt')->nullable()->index();
                $table->timestamps();
                $table->index(['userId', 'readAt']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_notifications');
        Schema::dropIfExists('staff_broadcasts');
        Schema::dropIfExists('staff_push_tokens');
    }
};
