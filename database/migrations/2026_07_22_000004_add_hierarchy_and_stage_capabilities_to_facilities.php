<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * parentFacilityId establishes the actual referral tree: a Hub has
     * SubHubs under it, a SubHub has Feeders under it. Referral routing
     * should walk this explicit tree rather than searching by geography
     * once a client already has an established facility relationship
     * (geography-based matching stays for Bloom's first-contact facility
     * assignment, where no relationship exists yet).
     *
     * stagesSupported is a JSON list of which patient-journey stages a
     * facility can perform (e.g. ["stage2"] or ["stage2","stage3"]).
     * This is intentionally NOT derived from facilityLevel in code —
     * an admin configures it per facility, so a SubHub or even a Feeder
     * that happens to have diagnostic capability can be marked
     * Stage 3-capable without a code change.
     */
    public function up(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->unsignedBigInteger('parentFacilityId')->nullable()->after('facilityLevel');
            $table->json('stagesSupported')->nullable()->after('parentFacilityId');

            $table->foreign('parentFacilityId')->references('facilityId')->on('facilities')->nullOnDelete();
        });

        // Reasonable starting defaults so nothing is left unconfigured —
        // admins should review and adjust per facility afterward.
        DB::table('facilities')->where('facilityLevel', 'hub')
            ->update(['stagesSupported' => json_encode(['stage2', 'stage3'])]);
        DB::table('facilities')->whereIn('facilityLevel', ['subhub', 'feeder'])
            ->update(['stagesSupported' => json_encode(['stage2'])]);
    }

    public function down(): void
    {
        Schema::table('facilities', function (Blueprint $table) {
            $table->dropForeign(['parentFacilityId']);
            $table->dropColumn(['parentFacilityId', 'stagesSupported']);
        });
    }
};
