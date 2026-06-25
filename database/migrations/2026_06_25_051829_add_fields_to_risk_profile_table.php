<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('client_risk_profiles', function (Blueprint $table) {
             $table->string('ageAtFirstMenstruation')->nullable();
             $table->string('ageAtMenopause')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('risk_profile', function (Blueprint $table) {
            //
        });
    }
};
