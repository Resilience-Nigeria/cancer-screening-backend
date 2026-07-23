<?php
// app/Services/SmsService.php

namespace App\Services;

use App\Models\NotificationProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Twilio\Rest\Client as TwilioClient;
use Twilio\Exceptions\TwilioException;

class SmsService
{
    protected ?TwilioClient $client = null;
    protected string $from = '';
    protected bool $enabled = true;
    protected bool $resolved = false;

    /**
     * Credential resolution is deferred to resolveConfig(), called from
     * send() — not done in the constructor. Laravel's console kernel can
     * resolve this class for any artisan command that depends on it
     * (directly or transitively), including `migrate` itself — querying
     * notification_providers in the constructor would fail on a fresh
     * migrate before that table exists. Same guard used everywhere else
     * this app talks to notification_providers.
     */
    protected function resolveConfig(): void
    {
        if ($this->resolved) {
            return;
        }
        $this->resolved = true;

        $dbConfig = [];

        // Checks the same notification_providers config used by
        // Settings > Notifications — credentials entered there (e.g.
        // for Twilio) are picked up here too, not just from .env.
        if (Schema::hasTable('notification_providers')) {
            $provider = NotificationProvider::where('channel', 'sms')
                ->where('providerKey', 'twilio')
                ->where('isActive', true)
                ->first();

            $dbConfig = $provider?->config ?? [];
        }

        $sid = $dbConfig['accountSid'] ?? config('services.twilio.sid', '');
        $authToken = $dbConfig['authToken'] ?? config('services.twilio.auth_token', '');
        $this->from = $dbConfig['fromNumber'] ?? config('services.twilio.from', '') ?? '';
        $this->enabled = array_key_exists('enabled', $dbConfig)
            ? filter_var($dbConfig['enabled'], FILTER_VALIDATE_BOOLEAN)
            : (config('services.twilio.enabled', true) ?? true);

        $sid = $sid ?? '';
        $authToken = $authToken ?? '';

        if (empty($sid) || empty($authToken)) {
            Log::warning('SmsService: Twilio credentials not configured.');
            return;
        }

        try {
            $this->client = new TwilioClient($sid, $authToken);
        } catch (\Throwable $e) {
            Log::error('SmsService: Failed to initialize Twilio client.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function send(string $to, string $message): bool
    {
        $this->resolveConfig();

        if (!$this->enabled) {
            Log::info('SmsService: disabled via TWILIO_SMS_ENABLED=false.');
            return false;
        }

        if (!$this->client) {
            Log::warning('SmsService: Twilio client not initialized.');
            return false;
        }

        if (empty($this->from)) {
            Log::warning('SmsService: no from-number configured.');
            return false;
        }

        $to = $this->normalizeNumber($to);

        if (empty($to)) {
            Log::warning('SmsService: invalid phone number.');
            return false;
        }

        $message = mb_substr($message, 0, 459);

        try {
            $sms = $this->client->messages->create($to, [
                'from' => $this->from,
                'body' => $message,
            ]);

            Log::info('SMS sent via Twilio', [
                'to'  => $to,
                'sid' => $sms->sid,
            ]);

            return true;

        } catch (TwilioException $e) {
            Log::error('SMS Twilio error', [
                'to'      => $to,
                'code'    => $e->getCode(),
                'message' => $e->getMessage(),
            ]);
            return false;

        } catch (\Throwable $e) {
            Log::error('SMS unexpected exception', [
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    protected function normalizeNumber(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '234') && strlen($phone) === 13) {
            return '+' . $phone;
        }

        if (str_starts_with($phone, '0') && strlen($phone) === 11) {
            return '+234' . substr($phone, 1);
        }

        if (strlen($phone) === 10) {
            return '+234' . $phone;
        }

        Log::warning('SmsService: could not normalize number', ['raw' => $phone]);
        return '';
    }
}
