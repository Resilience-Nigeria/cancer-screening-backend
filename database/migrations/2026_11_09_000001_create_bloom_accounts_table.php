<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts for the Bloom mobile app. Deliberately separate from `users`
 * (staff) and `clients` (registry records: those need a NIN, gender, DOB and a
 * facility, none of which a self-registering app user has). Foreign keys are
 * left out on purpose so the migration also runs on MyISAM shared hosting.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('bloom_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 20)->unique();          // stored as 234XXXXXXXXXX
            $table->string('password');
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('need', 30)->nullable();         // breast-health | screening | appointment | community
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloom_accounts');
    }
};
