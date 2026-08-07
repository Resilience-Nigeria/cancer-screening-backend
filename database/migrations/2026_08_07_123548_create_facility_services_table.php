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
        Schema::create('facility_services', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('facilityId');
    $table->foreign('facilityId')->references('facilityId')->on('facilities')->cascadeOnDelete();
    $table->unsignedBigInteger('serviceId');
    $table->foreign('serviceId')->references('serviceId')->on('services')->cascadeOnDelete();
    $table->boolean('isActive')->default(true);
    $table->timestamps();

    $table->unique(['facilityId', 'serviceId']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facility_services');
    }
};
