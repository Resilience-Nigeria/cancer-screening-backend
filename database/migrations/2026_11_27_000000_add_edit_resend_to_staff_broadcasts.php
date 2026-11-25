<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Staff notifications: edit, resend and withdraw history. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_broadcasts', function (Blueprint $t) {
            if (!Schema::hasColumn('staff_broadcasts', 'editedAt')) $t->timestamp('editedAt')->nullable();
            if (!Schema::hasColumn('staff_broadcasts', 'editedById')) $t->unsignedBigInteger('editedById')->nullable();
            if (!Schema::hasColumn('staff_broadcasts', 'resendCount')) $t->unsignedInteger('resendCount')->default(0);
            if (!Schema::hasColumn('staff_broadcasts', 'lastResentAt')) $t->timestamp('lastResentAt')->nullable();
            if (!Schema::hasColumn('staff_broadcasts', 'lastResentById')) $t->unsignedBigInteger('lastResentById')->nullable();
            if (!Schema::hasColumn('staff_broadcasts', 'withdrawnAt')) $t->timestamp('withdrawnAt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('staff_broadcasts', function (Blueprint $t) {
            $t->dropColumn(['editedAt', 'editedById', 'resendCount', 'lastResentAt', 'lastResentById', 'withdrawnAt']);
        });
    }
};
