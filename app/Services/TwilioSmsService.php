<?php

namespace App\Services;

use App\Models\NotificationProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Twilio\Rest\Client as TwilioClient;
use Twilio\Exceptions\TwilioException;

class TwilioSmsService
{
    protected ?TwilioClient $client = null;
    protected string $from = '';
    protected bool $resolved = false;

    /**
     * Credential resolution is deferred to resolveConfig(), called from
     * send() — not done in the constructor. Laravel's console kernel
     * can resolve this class for ANY artisan command (it's used from
     * SendFollowUpReminders), including `migrate` itself — querying
     * notification_providers in the constructor caused a fresh
     * `php artisan migrate` to fail before the table existed, for the
     * other notification services. Same guard applied here up front.
     */
    protected function resolveConfig(): void
    {
        if ($this->resolved) {
            return;
        }
        $this->resolved = true;

        $dbConfig = [];

        if (Schema::hasTable('notification_providers')) {
            $provider = NotificationProvider::where('channel', 'sms')
                ->where('providerKey', 'twilio')
                ->where('isActive', true)
                ->first();

            $dbConfig = $provider?->config ?? [];
        }

        $accountSid = $dbConfig['accountSid'] ?? config('services.twilio.sid');
        $authToken = $dbConfig['authToken'] ?? config('services.twilio.token');
        $this->from = $dbConfig['fromNumber'] ?? config('services.twilio.sms_from', '');

        if ($accountSid && $authToken) {
            try {
                $this->client = new TwilioClient($accountSid, $authToken);
            } catch (\Throwable $e) {
                Log::error('TwilioSmsService: failed to initialize Twilio client', ['error' => $e->getMessage()]);
                $this->client = null;
            }
        }
    }

    /**
     * Send a plain-text SMS via Twilio. Returns true only on a
     * confirmed successful send.
     */
    public function send(string $to, string $message): bool
    {
        $this->resolveConfig();

        if (!$this->client) {
            Log::warning('TwilioSmsService: Twilio client not initialized (missing accountSid/authToken).');
            return false;
        }

        if (!$this->from) {
            Log::warning('TwilioSmsService: no from-number configured.');
            return false;
        }

        $to = $this->normalizeNumber($to);
        if (empty($to)) {
            Log::warning('TwilioSmsService: invalid phone number.', ['raw' => $to]);
            return false;
        }

        try {
            $msg = $this->client->messages->create($to, [
                'from' => $this->from,
                'body' => $message,
            ]);

            Log::info('SMS sent via Twilio', ['to' => $to, 'sid' => $msg->sid]);
            return true;
        } catch (TwilioException $e) {
            Log::error('Twilio SMS error', [
                'to' => $to,
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('Twilio SMS unexpected exception', ['to' => $to, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Normalize to E.164 format for Nigerian numbers, e.g. +2348XXXXXXXXX.
     */
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

        return '';
    }
}
