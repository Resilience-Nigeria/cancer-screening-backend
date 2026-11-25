<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who registered each client (the logged-in nurse / navigator), so the mobile app can show "My clients".
 * Clients registered before this migration have no value (there is no record of who registered them).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('clients', 'registeredById')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->unsignedBigInteger('registeredById')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clients', 'registeredById')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropIndex(['registeredById']);
                $table->dropColumn('registeredById');
            });
        }
    }
};
