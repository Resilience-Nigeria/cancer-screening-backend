<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('breast_screenings', function (Blueprint $table) {
            $table->date('ihcBookingDate')->nullable();
            $table->unsignedBigInteger('ihcBookingFacilityId')->nullable();
            $table->text('ihcBookingNotes')->nullable();
        });

        Schema::table('cervical_screenings', function (Blueprint $table) {
            $table->boolean('hpvBookNow')->default(false);
            $table->date('hpvBookingDate')->nullable();
            $table->unsignedBigInteger('hpvBookingFacilityId')->nullable();
            $table->text('hpvBookingNotes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('breast_screenings', function (Blueprint $table) {
            $table->dropColumn(['ihcBookingDate', 'ihcBookingFacilityId', 'ihcBookingNotes']);
        });

        Schema::table('cervical_screenings', function (Blueprint $table) {
            $table->dropColumn(['hpvBookNow', 'hpvBookingDate', 'hpvBookingFacilityId', 'hpvBookingNotes']);
        });
    }
};