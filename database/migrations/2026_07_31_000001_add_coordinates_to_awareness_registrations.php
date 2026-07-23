<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Persists the resolved location coordinates on the registration
     * itself, rather than only using them transiently inside
     * FacilityService's nearest-facility matching. This is what
     * actually lets the client's approximate location be used later
     * (mapping, distance reporting) instead of being discarded right
     * after facility matching.
     */
    public function up(): void
    {
        Schema::table('awareness_registrations', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('areaOfResidence');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            // Records whether the coordinates came from an exact area
            // match (most precise) or fell back to the LGA's center —
            // useful context for anyone using this data later.
            $table->enum('coordinateSource', ['area', 'lga'])->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('awareness_registrations', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'coordinateSource']);
        });
    }
};
