<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phones using the NCSR Mobile App, so an admin can sign a lost / stolen phone out or wipe its data. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('staff_devices')) {
            Schema::create('staff_devices', function (Blueprint $t) {
                $t->id();
                $t->string('deviceId', 64)->unique();
                $t->unsignedBigInteger('userId')->nullable()->index();
                $t->string('platform', 20)->nullable();
                $t->string('model', 120)->nullable();
                $t->string('appVersion', 30)->nullable();
                $t->string('updateId', 64)->nullable();
                $t->unsignedInteger('pendingRecords')->default(0);
                $t->timestamp('lastSeenAt')->nullable();
                $t->string('lastIp', 45)->nullable();
                $t->timestamp('signOutRequestedAt')->nullable();
                $t->timestamp('wipeRequestedAt')->nullable();
                $t->unsignedBigInteger('requestedById')->nullable();
                $t->timestamp('signedOutAt')->nullable();
                $t->timestamp('wipedAt')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_devices');
    }
};
