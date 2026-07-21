<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Lets a specific user see specific additional facilities beyond
     * whatever their role's dataScopeType already grants — e.g. a Nurse
     * whose role scope is "facility_only" but who genuinely covers two
     * clinics. This is additive only: it can only grant extra visibility,
     * never take away what the role scope already provides, and has no
     * effect at all for a "national" scope role (already unrestricted).
     */
    public function up(): void
    {
        Schema::create('user_facility_grants', function (Blueprint $table) {
            $table->id('grantId');
            $table->unsignedBigInteger('userId');
            $table->unsignedBigInteger('facilityId');
            $table->unsignedBigInteger('grantedBy')->nullable();
            $table->timestamps();

            $table->unique(['userId', 'facilityId']);
            $table->foreign('userId')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('facilityId')->references('facilityId')->on('facilities')->cascadeOnDelete();
            $table->foreign('grantedBy')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_facility_grants');
    }
};
