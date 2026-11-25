<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** "Report a problem" from the app (with its sync log) and automatic crash / error reports. */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('userId')->nullable()->index();
                $t->string('category', 30);                 // sync | login | form | bug | other
                $t->text('message');
                $t->json('device')->nullable();
                $t->json('syncLog')->nullable();
                $t->string('status', 20)->default('open')->index();   // open | resolved
                $t->text('adminNote')->nullable();
                $t->unsignedBigInteger('resolvedById')->nullable();
                $t->timestamp('resolvedAt')->nullable();
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('app_error_reports')) {
            Schema::create('app_error_reports', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('userId')->nullable()->index();
                $t->string('deviceId', 64)->nullable()->index();
                $t->string('platform', 20)->nullable();
                $t->string('appVersion', 30)->nullable();
                $t->string('updateId', 64)->nullable();
                $t->string('screen', 120)->nullable();
                $t->boolean('fatal')->default(false);
                $t->string('message', 500);
                $t->text('stack')->nullable();
                $t->string('fingerprint', 64)->index();     // groups the same error
                $t->timestamp('occurredAt')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_error_reports');
        Schema::dropIfExists('support_tickets');
    }
};
