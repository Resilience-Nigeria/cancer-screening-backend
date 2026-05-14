<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id('id');
            $table->string('clientId')->unique();
            $table->unsignedBigInteger('facilityId')->nullable();
            // $table->string('screeningId')->unique();
            $table->string('fullName');
            $table->enum('gender', ['male', 'female']);
            $table->date('dateOfBirth');
            $table->string('age');
            $table->string('phoneNumber', 30)->nullable();
            $table->enum('screeningCategory', ['new_client', 'follow_up'])->default('new_client');
            $table->string('stateOfOrigin')->nullable();
            $table->string('lgaOfOrigin')->nullable();
            $table->string('stateOfResidence')->nullable();
            $table->string('lgaOfResidence')->nullable();
            $table->text('address')->nullable();
            $table->date('registrationDate');
            $table->timestamps();

            $table->index(['facilityId', 'fullName']);

            $table->foreign('facilityId')->references('facilityId')->on('facilities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};