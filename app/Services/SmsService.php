<?php
// app/Services/SmsService.php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as TwilioClient;
use Twilio\Exceptions\TwilioException;

class SmsService
{
    protected ?TwilioClient $client = null;
    protected string $from;
    protected bool $enabled;

    public function __construct()
    {
        $sid           = config('services.twilio.sid',        '');
        $authToken     = config('services.twilio.auth_token', '');
        $this->from    = config('services.twilio.from',       '');
        $this->enabled = config('services.twilio.enabled',    true);

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
        if (!$this->enabled) {
            Log::info('SmsService: disabled via TWILIO_SMS_ENABLED=false.');
            return false;
        }

        if (!$this->client) {
            Log::warning('SmsService: Twilio client not initialized.');
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