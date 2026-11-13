<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - A case can now also start from an NCSR client (a health worker asked to follow her up and an admin
 *   approved), so it may have no Bloom booking/account: appointment_id and account_id become nullable.
 * - bloom_access_requests: a health worker asks to attend to a client at her facility; an admin decides.
 * - bloom_staff_notifications: the health worker's bell (new case, request decided, booking confirmed...).
 */
return new class extends Migration {
    public function up(): void
    {
        // Raw SQL so no doctrine/dbal is needed (MySQL / MariaDB).
        DB::statement('ALTER TABLE bloom_cases MODIFY appointment_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE bloom_cases MODIFY account_id BIGINT UNSIGNED NULL');

        Schema::create('bloom_access_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();       // the health worker (users.id)
            $table->string('client_id', 40)->index();             // clients.clientId
            $table->unsignedBigInteger('facility_id')->index();
            $table->string('reason', 500);
            $table->string('status', 12)->default('pending')->index(); // pending | approved | rejected
            $table->string('decision_note', 300)->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->unsignedBigInteger('case_id')->nullable();      // the case opened on approval
            $table->timestamps();
        });

        Schema::create('bloom_staff_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('type', 40);
            $table->string('title', 150);
            $table->string('body', 500)->nullable();
            $table->unsignedBigInteger('case_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bloom_staff_notifications');
        Schema::dropIfExists('bloom_access_requests');
        // appointment_id / account_id are left nullable (rows without them may exist).
    }
};
