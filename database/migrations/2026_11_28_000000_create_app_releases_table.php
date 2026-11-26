<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** NCSR Mobile App versions (APKs) published from the web dashboard, so phones can update themselves. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_releases', function (Blueprint $t) {
            $t->id();
            $t->string('version', 20);                    // e.g. 1.2.0 (app.json "version")
            $t->unsignedInteger('versionCode')->unique();  // e.g. 5 (app.json android.versionCode) — must go up with every build
            $t->text('notes')->nullable();                 // "What's new", shown on the phone
            $t->string('apkPath')->nullable();             // uploaded APK (storage/app/apk/...)
            $t->string('apkUrl', 1000)->nullable();        // …or an outside link (e.g. Google Drive)
            $t->unsignedBigInteger('apkSize')->nullable();
            $t->string('sha256', 64)->nullable();
            $t->string('token', 40)->unique();             // unguessable part of the download link
            $t->boolean('mandatory')->default(false);      // phones older than this must update before continuing
            $t->boolean('published')->default(false);
            $t->timestamp('publishedAt')->nullable();
            $t->unsignedInteger('downloads')->default(0);
            $t->unsignedBigInteger('createdById')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
    }
};
