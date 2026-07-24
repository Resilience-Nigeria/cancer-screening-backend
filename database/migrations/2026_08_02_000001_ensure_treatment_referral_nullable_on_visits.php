<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * The original migration declares screening_visits.treatmentReferral
     * as nullable, but a live database was observed rejecting NULL
     * inserts with a "cannot be null" constraint violation - meaning
     * that database's actual column definition has drifted from what
     * the migration history says it should be (likely from a manual
     * schema change made outside migrations at some point). This
     * explicitly forces it back to nullable regardless of that history,
     * using raw SQL to avoid depending on doctrine/dbal for a column
     * MODIFY.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE screening_visits MODIFY treatmentReferral TINYINT(1) NULL');
    }

    public function down(): void
    {
        // Not reversed - reverting to NOT NULL would risk breaking
        // existing NULL rows written since this migration ran.
    }
};
