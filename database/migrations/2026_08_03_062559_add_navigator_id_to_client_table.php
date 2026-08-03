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
        // Schema::table('clients', function (Blueprint $table) {
        // $table->unsignedBigInteger('navigatorId')->nullable();  
        // $table->foreign('navigatorId')->references('id')->on('navigators')->nullOnDelete();
        // });
   
        // Schema::table('awareness_registrations', function (Blueprint $table) {
        // $table->unsignedBigInteger('navigatorId')->nullable();  
        // $table->foreign('navigatorId')->references('id')->on('navigators')->nullOnDelete();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client', function (Blueprint $table) {
            //
        });
    }
};
