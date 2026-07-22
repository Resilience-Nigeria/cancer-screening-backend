<?php
// app/Services/WhatsAppService.php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as TwilioClient;
use Twilio\Exceptions\TwilioException;

class WhatsAppService
{
    protected ?TwilioClient $client = null;
    protected string $from;
    protected bool $enabled;

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