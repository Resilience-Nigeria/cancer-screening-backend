<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->json('clinicDays')->nullable()->after('facilityAddress');
            $table->string('clinicOpenTime')->nullable()->after('clinicDays');
            $table->string('clinicCloseTime')->nullable()->after('clinicOpenTime');
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn(['clinicDays', 'clinicOpenTime', 'clinicCloseTime']);
        });
    }
};
