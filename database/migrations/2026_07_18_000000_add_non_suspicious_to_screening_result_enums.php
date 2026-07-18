<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Root cause of the recurring "invalid screening result" errors:
     * screeningResult was created as ENUM('negative','positive','suspicious')
     * on all five screening tables, but the Http Requests (and the frontend
     * wizard) validate/send 'non_suspicious' as well. App-level validation
     * passes, then the DB rejects/truncates the write — surfacing to
     * clinicians as an "invalid" error with no useful message.
     */
    protected array $tables = [
        'breast_screenings',
        'cervical_screenings',
        'colorectal_screenings',
        'liver_screenings',
        'prostate_screenings',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'screeningResult')) {
                continue;
            }

            DB::statement("
                ALTER TABLE `{$table}`
                MODIFY `screeningResult`
                ENUM('negative','positive','suspicious','non_suspicious')
                NOT NULL
            ");
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'screeningResult')) {
                continue;
            }

            // Reverting is destructive if any row already holds 'non_suspicious'.
            // Coerce those rows to 'suspicious' first so the narrower enum can be restored.
            DB::table($table)
                ->where('screeningResult', 'non_suspicious')
                ->update(['screeningResult' => 'suspicious']);

            DB::statement("
                ALTER TABLE `{$table}`
                MODIFY `screeningResult`
                ENUM('negative','positive','suspicious')
                NOT NULL
            ");
        }
    }
};
