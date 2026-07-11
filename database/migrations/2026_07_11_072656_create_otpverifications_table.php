<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // database/migrations/xxxx_create_otp_verifications_table.php
public function up(): void
{
    Schema::create('otp_verifications', function (Blueprint $table) {
        $table->id('otpId');
        $table->string('phoneNumber');
        $table->string('otp', 6);
        $table->string('registrationId')->nullable();
        $table->boolean('verified')->default(false);
        $table->timestamp('expiresAt');
        $table->timestamps();

        $table->index('phoneNumber');
    });
}

public function down(): void
{
    Schema::dropIfExists('otp_verifications');
}


};
