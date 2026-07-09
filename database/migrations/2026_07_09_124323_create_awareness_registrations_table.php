<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // database/migrations/xxxx_create_awareness_registrations_table.php
public function up(): void
{
    Schema::create('awarenessRegistrations', function (Blueprint $table) {
        $table->id('registrationId');
        $table->string('fullName');
        $table->enum('gender', ['male', 'female']);
        $table->string('phoneNumber');
        $table->string('email')->nullable();
        $table->string('stateOfResidence');
        $table->string('lgaOfResidence');
        $table->string('campaignSource')->nullable(); // which QR / campaign
        $table->enum('status', ['pending', 'linked', 'converted'])
              ->default('pending');
        // Set when converted to a full client record
        $table->string('clientId')->nullable();
        $table->foreign('clientId')->references('clientId')->on('clients')->nullOnDelete();
        $table->timestamps();
    });
}

public function down(): void
{
    Schema::dropIfExists('awarenessRegistrations');
}
};
