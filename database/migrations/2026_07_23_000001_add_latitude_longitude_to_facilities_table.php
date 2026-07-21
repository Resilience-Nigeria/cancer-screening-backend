<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * The original "add_coordinates_to_facilities_table" migration
     * (2026_07_11_222502) has an empty body — it never actually added
     * these columns, even though FacilityService has been querying
     * whereNotNull('latitude')/('longitude') against facilities the
     * whole time. That would throw "Unknown column" every time a
     * request reached the distance-based matching step (whenever the
     * exact LGA match failed). This is a genuinely new migration file
     * rather than an edit to the old one, since Laravel tracks
     * completed migrations by filename — editing the old file
     * wouldn't re-run it if it's already marked as applied.
     */
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            if (!Schema::hasColumn('facilities', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('facilities', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
