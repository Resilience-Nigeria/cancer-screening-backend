<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lets staff confirm (or decline) a request with the real slot and a note for the client. */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('bloom_appointment_requests', function (Blueprint $table) {
            $table->date('confirmed_date')->nullable();
            $table->string('confirmed_time', 5)->nullable();      // HH:MM, 24h
            $table->string('staff_note', 500)->nullable();        // shown to the client
            $table->unsignedBigInteger('decided_by')->nullable(); // users.id of the staff member
            $table->timestamp('decided_at')->nullable();
            $table->unsignedTinyInteger('reschedule_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('bloom_appointment_requests', function (Blueprint $table) {
            $table->dropColumn(['confirmed_date', 'confirmed_time', 'staff_note', 'decided_by', 'decided_at', 'reschedule_count']);
        });
    }
};
