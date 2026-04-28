<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('prostate_screenings', function (Blueprint $table) {
            // Add missing fields for prostate screening
            if (!Schema::hasColumn('prostate_screenings', 'poorUrinaryStream')) {
                $table->enum('poorUrinaryStream', ['yes', 'no'])->nullable();
            }
            if (!Schema::hasColumn('prostate_screenings', 'urgeIncontinence')) {
                $table->enum('urgeIncontinence', ['yes', 'no'])->nullable();
            }
            if (!Schema::hasColumn('prostate_screenings', 'delayStartingUrination')) {
                $table->enum('delayStartingUrination', ['yes', 'no'])->nullable();
            }
            if (!Schema::hasColumn('prostate_screenings', 'inabilityToHoldUrine')) {
                $table->enum('inabilityToHoldUrine', ['yes', 'no'])->nullable();
            }
            if (!Schema::hasColumn('prostate_screenings', 'terminalDribbling')) {
                $table->enum('terminalDribbling', ['yes', 'no'])->nullable();
            }
            if (!Schema::hasColumn('prostate_screenings', 'frequentDayUrination')) {
                $table->enum('frequentDayUrination', ['yes', 'no'])->nullable();
            }
            if (!Schema::hasColumn('prostate_screenings', 'nocturia')) {
                $table->enum('nocturia', ['yes', 'no'])->nullable();
            }
            if (!Schema::hasColumn('prostate_screenings', 'incompleteEmptying')) {
                $table->enum('incompleteEmptying', ['yes', 'no'])->nullable();
            }
            if (!Schema::hasColumn('prostate_screenings', 'bloodInUrine')) {
                $table->enum('bloodInUrine', ['yes', 'no'])->nullable();
            }
            if (!Schema::hasColumn('prostate_screenings', 'referral')) {
                $table->enum('referral', ['referred', 'not_referred'])->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('prostate_screenings', function (Blueprint $table) {
            $table->dropColumn([
                'poorUrinaryStream',
                'urgeIncontinence',
                'delayStartingUrination',
                'inabilityToHoldUrine',
                'terminalDribbling',
                'frequentDayUrination',
                'nocturia',
                'incompleteEmptying',
                'bloodInUrine',
                'referral',
            ]);
        });
    }
};