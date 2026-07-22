<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Configured notification providers per channel (sms/email/whatsapp).
     * Each row is one provider's credentials; exactly one per channel
     * can be isDefault at a time. Services check here first and fall
     * back to .env config if nothing is configured, so this is additive
     * rather than a breaking change to existing deployments.
     */
    public function up(): void
    {
        Schema::create('notification_providers', function (Blueprint $table) {
            $table->id('providerId');
            $table->enum('channel', ['sms', 'email', 'whatsapp']);
            $table->string('providerKey'); // e.g. 'bulksms', 'twilio', 'brevo', 'sendgrid', 'whatsapp_meta'
            $table->string('providerName'); // display name
            $table->json('config'); // provider-specific credential fields
            $table->boolean('isActive')->default(true);
            $table->boolean('isDefault')->default(false);
            $table->timestamps();

            $table->unique(['channel', 'providerKey']);
        });

        // Seed the 3 providers already implemented in code, pulling
        // whatever is currently in .env so nothing changes behavior —
        // this just makes the existing configuration visible/editable.
        $seeds = [
            [
                'channel' => 'sms',
                'providerKey' => 'bulksms',
                'providerName' => 'BulkSMS Nigeria',
                'config' => json_encode([
                    'apiToken' => config('services.bulksms_nigeria.api_token', ''),
                    'senderId' => config('services.bulksms_nigeria.sender_id', 'NCSR'),
                    'sandbox' => (bool) config('services.bulksms_nigeria.sandbox', false),
                ]),
                'isDefault' => true,
            ],
            [
                'channel' => 'email',
                'providerKey' => 'brevo',
                'providerName' => 'Brevo',
                'config' => json_encode([
                    'apiKey' => config('services.brevo.key', ''),
                    'fromEmail' => config('services.brevo.from_email', ''),
                    'fromName' => config('services.brevo.from_name', 'NCSR'),
                ]),
                'isDefault' => true,
            ],
            [
                'channel' => 'whatsapp',
                'providerKey' => 'whatsapp_meta',
                'providerName' => 'WhatsApp (Meta Cloud API)',
                'config' => json_encode([
                    'apiUrl' => config('services.whatsapp.api_url', ''),
                    'token' => config('services.whatsapp.token', ''),
                    'phoneNumberId' => config('services.whatsapp.phone_number_id', ''),
                ]),
                'isDefault' => true,
            ],
        ];

        foreach ($seeds as $seed) {
            DB::table('notification_providers')->insert([
                ...$seed,
                'isActive' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_providers');
    }
};
