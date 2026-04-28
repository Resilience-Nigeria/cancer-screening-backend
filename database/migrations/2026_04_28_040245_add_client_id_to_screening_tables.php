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
        // Schema::table('breast_screenings', function (Blueprint $table) {
        //     $table->unsignedBigInteger('clientId')->nullable();
        //     $table->foreign('clientId')->references('clientId')->on('clients')->nullOnDelete();
        // });

        // Schema::table('cervical_screenings', function (Blueprint $table) {
        //     $table->unsignedBigInteger('clientId')->nullable();
        //     $table->foreign('clientId')->references('clientId')->on('clients')->nullOnDelete();
        // });

        // Schema::table('prostate_screenings', function (Blueprint $table) {
        //     $table->unsignedBigInteger('clientId')->nullable();
        //     $table->foreign('clientId')->references('clientId')->on('clients')->nullOnDelete();
        // });


        // Schema::table('colorectal_screenings', function (Blueprint $table) {
        //     $table->unsignedBigInteger('clientId')->nullable();
        //     $table->foreign('clientId')->references('clientId')->on('clients')->nullOnDelete();
        // });


        Schema::table('liver_screenings', function (Blueprint $table) {
            // $table->unsignedBigInteger('clientId')->nullable();
            // $table->foreign('clientId')->references('clientId')->on('clients')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('screening_tables', function (Blueprint $table) {
            //
        });
    }
};
