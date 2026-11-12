<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('client_id_changes', function (Blueprint $table) {
            $table->id();
            $table->string('oldClientId')->index();
            $table->string('newClientId')->index();
            $table->string('oldStateOfResidence')->nullable();
            $table->string('oldLgaOfResidence')->nullable();
            $table->string('newStateOfResidence')->nullable();
            $table->string('newLgaOfResidence')->nullable();
            $table->string('reason', 500)->nullable();
            $table->unsignedBigInteger('changedById')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_id_changes');
    }
};
