<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    // database/migrations/xxxx_create_area_coordinates_table.php
public function up(): void
{
    Schema::create('area_coordinates', function (Blueprint $table) {
        $table->id();
        $table->string('state');
        $table->string('lga');
        $table->string('area');           // district / ward / town
        $table->decimal('latitude',  10, 7);
        $table->decimal('longitude', 10, 7);
        $table->timestamps();

        $table->index(['state', 'lga']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('area_coordinates');
    }
};
