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
        Schema::table('follow_up_schedules', function (Blueprint $table) {
    $table->unsignedBigInteger('treatmentPlanId')->nullable()->change();
    $table->unsignedBigInteger('visitId')->nullable()->after('treatmentPlanId');
    $table->string('clientId', 64)->nullable()->after('visitId'); // match your client PK type
    $table->string('source')->nullable()->after('clientId'); // e.g. stage2_outcome | treatment_plan
    $table->string('reason')->nullable(); // e.g. routine | low_suspicion

    $table->foreign('visitId')->references('visitId')->on('screening_visits')->nullOnDelete();
    $table->foreign('clientId')->references('clientId')->on('clients')->nullOnDelete();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
