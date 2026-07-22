<?php
// app/Services/WhatsAppService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected string $apiUrl;
    protected string $apiToken;
    protected string $phoneNumberId;

    public function __construct()
    {
        $dbProvider = \App\Models\NotificationProvider::where('channel', 'whatsapp')
            ->where('providerKey', 'whatsapp_meta')
            ->where('isActive', true)
            ->first();

        $dbConfig = $dbProvider?->config ?? [];

        $this->apiUrl        = $dbConfig['apiUrl'] ?: config('services.whatsapp.api_url');
        $this->apiToken      = $dbConfig['token'] ?: config('services.whatsapp.token');
        $this->phoneNumberId = $dbConfig['phoneNumberId'] ?: config('services.whatsapp.phone_number_id');
    }

    /**
     * Send a plain-text WhatsApp message.
     */
    public function send(string $to, string $message): bool
{
    $to = $this->normalizeNumber($to);

    Log::info('WhatsApp send attempt', [
        'to'             => $to,
        'phone_number_id'=> $this->phoneNumberId,
        'api_url'        => $this->apiUrl,
        'message_length' => strlen($message),
    ]);

    try {
        $response = Http::withToken($this->apiToken)
            ->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", [
                'messaging_product' => 'whatsapp',
                'to'                => $to,
                'type'              => 'text',
                'text'              => ['body' => $message],
            ]);

        Log::info('WhatsApp API response', [
            'to'     => $to,
            'status' => $response->status(),
            'body'   => $response->json(),
        ]);

        if (!$response->successful()) {
            Log::error('WhatsApp send failed', [
                'to'     => $to,
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);
            return false;
        }

        return true;
    } catch (\Throwable $e) {
        Log::error('WhatsApp exception', [
            'to'    => $to,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return false;
    }
}
    /**
     * Send a template message (for pre-approved WhatsApp templates).
     */
    public function sendTemplate(
        string $to,
        string $templateName,
        string $languageCode = 'en',
        array $components = []
    ): bool {
        $to = $this->normalizeNumber($to);

        try {
            $response = Http::withToken($this->apiToken)
                ->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to'                => $to,
                    'type'              => 'template',
                    'template'          => [
                        'name'       => $templateName,
                        'language'   => ['code' => $languageCode],
                        'components' => $components,
                    ],
                ]);

            if (!$response->successful()) {
                Log::warning('WhatsApp template send failed', [
                    'to'       => $to,
                    'template' => $templateName,
                    'body'     => $response->json(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('WhatsApp template exception', [
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Normalize Nigerian phone numbers to international format (234XXXXXXXXXX).
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