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
        Schema::create('services', function (Blueprint $table) {
    $table->id('serviceId');
    $table->string('name');           // "Biopsy", "Mammography", "Colposcopy", "PSA Test"
    $table->string('slug')->unique(); // "biopsy", "mammography" — stable key for code to reference
    $table->string('category')->nullable(); // "diagnostic", "imaging", "lab" — optional grouping
    $table->boolean('isActive')->default(true);
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
