<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * The original notification_providers seed assumed WhatsApp used the
     * Meta Cloud API (apiUrl/token/phoneNumberId). The real implementation
     * is Twilio-based — this replaces that seeded row with the correct
     * provider key and fields. Existing values aren't guessed from .env
     * here since the actual config keys weren't confirmed; fill them in
     * via Settings > Notifications after migrating.
     */
    public function up(): void
    {
        DB::table('notification_providers')
            ->where('channel', 'whatsapp')
            ->where('providerKey', 'whatsapp_meta')
            ->delete();

        $exists = DB::table('notification_providers')
            ->where('channel', 'whatsapp')
            ->where('providerKey', 'twilio_whatsapp')
            ->exists();

        if (!$exists) {
            DB::table('notification_providers')->insert([
                'channel' => 'whatsapp',
                'providerKey' => 'twilio_whatsapp',
                'providerName' => 'WhatsApp (Twilio)',
                'config' => json_encode([
                    'accountSid' => '',
                    'authToken' => '',
                    'fromNumber' => '',
                    'enabled' => true,
                ]),
                'isActive' => true,
                'isDefault' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('notification_providers')
            ->where('channel', 'whatsapp')
            ->where('providerKey', 'twilio_whatsapp')
            ->delete();
    }
};
