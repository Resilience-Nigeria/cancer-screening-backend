<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Blood pressure needs no migration: bloodPressureSystolic / bloodPressureDiastolic already exist on
// visit_examinations (and client_risk_profiles).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_risk_profiles', function (Blueprint $table) {
            $table->string('ionizingRadiationExposure', 10)->nullable(); // yes / no
            $table->string('ionizingRadiationType', 20)->nullable();     // xray / ct_scan / other
            $table->string('ionizingRadiationOther', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('client_risk_profiles', function (Blueprint $table) {
            $table->dropColumn(['ionizingRadiationExposure', 'ionizingRadiationType', 'ionizingRadiationOther']);
        });
    }
};