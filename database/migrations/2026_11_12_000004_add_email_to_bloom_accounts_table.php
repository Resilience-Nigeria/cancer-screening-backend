<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bloom_accounts', function (Blueprint $table) {
            $table->string('email', 150)->nullable()->unique()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('bloom_accounts', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn('email');
        });
    }
};
