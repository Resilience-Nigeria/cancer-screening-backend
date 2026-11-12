<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Results are now grouped as non-suspicious (normal), suspicious (urgent) or inconclusive (neutral).
 * Existing "abnormal" results become suspicious.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('screening_samples')->where('resultSeverity', 'abnormal')->update(['resultSeverity' => 'urgent']);
    }

    public function down(): void
    {
        // Not reversible: the former "abnormal" rows can't be told apart from "urgent" ones afterwards.
    }
};
