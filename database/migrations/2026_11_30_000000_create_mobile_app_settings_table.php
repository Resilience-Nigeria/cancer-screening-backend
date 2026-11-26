<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Settings for the NCSR Mobile App (e.g. the "Get the app" prompt on the web). Key → JSON value. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_app_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key', 60)->unique();
            $t->json('value')->nullable();
            $t->unsignedBigInteger('updatedById')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_app_settings');
    }
};
