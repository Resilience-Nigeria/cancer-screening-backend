<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Change this if your cervical screening table is named differently.
    private string $cervicalTable = 'cervical_screenings';

    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->boolean('phoneNotClients')->default(false);
            $table->string('caregiverName')->nullable();
            $table->string('caregiverPhoneNumber', 30)->nullable();
            $table->boolean('otpSkipped')->default(false);
            $table->string('otpSkipReason')->nullable();
            $table->unsignedBigInteger('otpSkippedBy')->nullable();
        });

        if (!Schema::hasTable($this->cervicalTable)) {
            throw new RuntimeException("Table {$this->cervicalTable} not found. Set \$cervicalTable to your cervical screening table.");
        }

        Schema::table($this->cervicalTable, function (Blueprint $table) {
            $table->date('hpvSampleDate')->nullable();
            $table->date('hpvResultDate')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['phoneNotClients', 'caregiverName', 'caregiverPhoneNumber', 'otpSkipped', 'otpSkipReason', 'otpSkippedBy']);
        });

        Schema::table($this->cervicalTable, function (Blueprint $table) {
            $table->dropColumn(['hpvSampleDate', 'hpvResultDate']);
        });
    }
};