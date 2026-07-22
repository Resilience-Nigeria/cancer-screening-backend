<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BulkSMS Nigeria (https://www.bulksmsnigeria.com/app/api/docs) integration.
 *
 * Used as the fallback SMS channel for OTP delivery — WhatsApp is tried
 * first (OtpService), and this fires only if that fails, so every phone
 * number gets a code even if the recipient doesn't have WhatsApp.
 */
class BulkSmsService
{
    protected string $baseUrl;
    protected string $apiToken;
    protected string $senderId;

    public function __construct()
    {
        $dbProvider = \App\Models\NotificationProvider::where('channel', 'sms')
            ->where('providerKey', 'bulksms')
            ->where('isActive', true)
            ->first();

        $dbConfig = $dbProvider?->config ?? [];

        $sandbox = $dbConfig['sandbox'] ?? (bool) config('services.bulksms_nigeria.sandbox');

        $this->baseUrl = $sandbox
            ? 'https://www.bulksmsnigeria.com/api/sandbox/v2'
            : 'https://www.bulksmsnigeria.com/api/v2';

        $this->apiToken = (string) ($dbConfig['apiToken'] ?: config('services.bulksms_nigeria.api_token'));
        $this->senderId = (string) ($dbConfig['senderId'] ?: config('services.bulksms_nigeria.sender_id', 'NCSR'));
    }

    /**
     * Send a plain-text SMS. Returns true only on a confirmed "success" response.
     */
    public function send(string $to, string $message): bool
    {
        $to = $this->normalizeNumber($to);

        if (!$this->apiToken) {
            Log::error('BulkSMS Nigeria send skipped — no API token configured', ['to' => $to]);
            return false;
        }

        Log::info('BulkSMS Nigeria send attempt', [
            'to' => $to,
            'message_length' => strlen($message),
        ]);

        try {
            $response = Http::withToken($this->apiToken)
                ->acceptJson()
                ->post("{$this->baseUrl}/sms", [
                    'from' => $this->senderId,
                    'to' => $to,
                    'body' => $message,
                ]);

            $body = $response->json();

            Log::info('BulkSMS Nigeria API response', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $body,
            ]);

            if (!$response->successful() || ($body['status'] ?? null) !== 'success') {
                Log::error('BulkSMS Nigeria send failed', [
                    'to' => $to,
                    'status' => $response->status(),
                    'body' => $body,
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('BulkSMS Nigeria exception', [
                'to' => $to,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Normalize Nigerian phone numbers to international format (234XXXXXXXXXX)
     * with no leading '+', matching BulkSMS Nigeria's expected `to` format.
     */
    protected function normalizeNumber(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '0')) {
            $phone = '234' . substr($phone, 1);
        }

        if (!str_starts_with($phone, '234')) {
            $phone = '234' . $phone;
        }

        return $phone;
    }
}
