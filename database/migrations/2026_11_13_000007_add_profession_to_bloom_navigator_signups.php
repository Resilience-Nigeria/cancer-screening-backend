<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Health worker sign-up now asks for their profession and staff ID (to help verification). */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('bloom_navigator_signups', function (Blueprint $table) {
            $table->string('profession', 60)->nullable()->after('facility_id');
            $table->string('staff_id', 50)->nullable()->after('profession');
        });
    }

    public function down(): void
    {
        Schema::table('bloom_navigator_signups', function (Blueprint $table) {
            $table->dropColumn(['profession', 'staff_id']);
        });
    }
};
