<?php
// app/Services/WhatsAppService.php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Twilio\Rest\Client as TwilioClient;
use Twilio\Exceptions\TwilioException;

class WhatsAppService
{
    protected ?TwilioClient $client = null;
    protected string $from = '';
    protected bool $enabled = false;
    protected bool $resolved = false;

    /**
     * Credential resolution is deferred until the first actual send,
     * not done in the constructor. Laravel's console kernel can resolve
     * this class out of the container for ANY artisan command (it's a
     * dependency of SendFollowUpReminders), including `migrate` itself —
     * querying notification_providers in the constructor meant a fresh
     * `php artisan migrate` failed before the table even existed.
     */
    protected function resolveConfig(): void
    {
        if ($this->resolved) {
            return;
        }
        $this->resolved = true;

        $dbConfig = [];

        if (Schema::hasTable('notification_providers')) {
            $dbProvider = \App\Models\NotificationProvider::where('channel', 'whatsapp')
                ->where('providerKey', 'twilio_whatsapp')
                ->where('isActive', true)
                ->first();

            $dbConfig = $dbProvider?->config ?? [];
        }

        // NOTE: these config('services.twilio.*') fallback keys are my
        // best guess at your existing .env mapping based on the
        // TWILIO_WHATSAPP_ENABLED reference in your log message — please
        // check these against your actual config/services.php and adjust
        // if the keys don't match.
        $accountSid = $dbConfig['accountSid'] ?? config('services.twilio.sid');
        $authToken  = $dbConfig['authToken'] ?? config('services.twilio.token');
        $this->from = $dbConfig['fromNumber'] ?? config('services.twilio.whatsapp_from', '');
        $this->enabled = array_key_exists('enabled', $dbConfig)
            ? filter_var($dbConfig['enabled'], FILTER_VALIDATE_BOOLEAN)
            : (bool) config('services.twilio.whatsapp_enabled', true);

        if ($accountSid && $authToken) {
            try {
                $this->client = new TwilioClient($accountSid, $authToken);
            } catch (\Throwable $e) {
                Log::error('WhatsAppService: failed to initialize Twilio client', ['error' => $e->getMessage()]);
                $this->client = null;
            }
        }
    }

    /**
     * Send a WhatsApp message via Twilio.
     * Returns:
     *   'sent'            — delivered successfully
     *   'not_on_whatsapp' — recipient not a WhatsApp user / not reachable
     *   'failed'          — other failure
     */
    public function sendWithStatus(string $to, string $message): string
    {
        $this->resolveConfig();

        if (!$this->enabled) {
            Log::info('WhatsAppService: disabled via TWILIO_WHATSAPP_ENABLED=false.');
            return 'failed';
        }

        if (!$this->client) {
            Log::warning('WhatsAppService: Twilio client not initialized.');
            return 'failed';
        }

        $to = $this->normalizeNumber($to);

        if (empty($to)) {
            Log::warning('WhatsAppService: invalid phone number.');
            return 'failed';
        }

        try {
            $msg = $this->client->messages->create($to, [
                'from' => $this->from,
                'body' => $message,
            ]);

            Log::info('WhatsApp sent via Twilio', [
                'to'  => $to,
                'sid' => $msg->sid,
            ]);

            return 'sent';

        } catch (TwilioException $e) {
            $code = $e->getCode();

            Log::error('WhatsApp Twilio error', [
                'to'      => $to,
                'code'    => $code,
                'message' => $e->getMessage(),
            ]);

            // These codes mean the recipient is not reachable on WhatsApp
            // 63003 — capability not enabled for destination
            // 63016 — failed to send message — recipient not a WhatsApp user
            // 63032 — recipient phone not registered on WhatsApp
            if (in_array($code, [63003, 63016, 63032, 21211])) {
                return 'not_on_whatsapp';
            }

            return 'failed';

        } catch (\Throwable $e) {
            Log::error('WhatsApp unexpected exception', [
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);
            return 'failed';
        }
    }

    /**
     * Convenience wrapper — returns bool.
     */
    public function send(string $to, string $message): bool
    {
        return $this->sendWithStatus($to, $message) === 'sent';
    }

    /**
     * Normalize to Twilio WhatsApp format: whatsapp:+2348XXXXXXXXX
     */
    protected function normalizeNumber(string $phone): string
    {
        // Already in Twilio WhatsApp format
        if (str_starts_with($phone, 'whatsapp:')) {
            return $phone;
        }

        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '234') && strlen($phone) === 13) {
            return 'whatsapp:+' . $phone;
        }

        if (str_starts_with($phone, '0') && strlen($phone) === 11) {
            return 'whatsapp:+234' . substr($phone, 1);
        }

        if (strlen($phone) === 10) {
            return 'whatsapp:+234' . $phone;
        }

        Log::warning('WhatsAppService: could not normalize number', [
            'raw' => $phone,
        ]);

        return '';
    }
}
