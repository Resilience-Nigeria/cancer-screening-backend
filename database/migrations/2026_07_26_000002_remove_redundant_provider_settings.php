<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * sms_provider/email_provider in system_settings were a simple
     * dropdown with only one hardcoded option each. Superseded by the
     * notification_providers table, which supports adding/removing
     * providers with their own credentials — keeping both would leave
     * two disagreeing sources of truth for "which provider is active".
     */
    public function up(): void
    {
        DB::table('system_settings')->whereIn('key', ['sms_provider', 'email_provider'])->delete();
    }

    public function down(): void
    {
        // Not restored — notification_providers is now the source of truth.
    }
};
