<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * The original migration created `awarenessRegistrations` (camelCase),
     * but AwarenessRegistration::$table expects `awareness_registrations`
     * (snake_case, matching every other table in this app). On
     * case-sensitive hosting (Linux/cPanel) these are two different
     * tables, so every AwarenessRegistration::create()/query has likely
     * been failing with a "table doesn't exist" error in production.
     */
    public function up(): void
    {
        if (Schema::hasTable('awarenessRegistrations') && !Schema::hasTable('awareness_registrations')) {
            Schema::rename('awarenessRegistrations', 'awareness_registrations');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('awareness_registrations') && !Schema::hasTable('awarenessRegistrations')) {
            Schema::rename('awareness_registrations', 'awarenessRegistrations');
        }
    }
};
