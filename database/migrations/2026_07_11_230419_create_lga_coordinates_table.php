<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgaCoordinates', function (Blueprint $table) {
            $table->id();
            $table->string('state');
            $table->string('lga');
            $table->decimal('latitude',  10, 7);
            $table->decimal('longitude', 10, 7);
            $table->timestamps();

            $table->index(['state', 'lga']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgaCoordinates');
    }
};