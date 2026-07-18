<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'email')) {
                // Nullable at the DB level so existing rows aren't broken;
                // enforced as required going forward in StoreClientRequest.
                $table->string('email')->nullable()->after('phoneNumber');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};
