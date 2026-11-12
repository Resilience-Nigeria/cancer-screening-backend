<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bloom_appointment_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->index();   // bloom_accounts.id
            $table->unsignedBigInteger('facility_id')->index();  // facilities.facilityId
            $table->string('type', 20)->default('general');
            $table->date('preferred_date');
            $table->string('preferred_time', 5)->nullable();     // HH:MM, 24h
            $table->string('note', 500)->nullable();
            $table->string('status', 20)->default('pending');    // pending | confirmed | cancelled | completed
            $table->timestamps();

            $table->index(['facility_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloom_appointment_requests');
    }
};
