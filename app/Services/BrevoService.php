<?php
// app/Services/BrevoService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoService
{
protected string $apiKey    = '';
protected string $fromEmail = '';
protected string $fromName  = '';
protected string $apiUrl    = 'https://api.brevo.com/v3';

   public function __construct()
{
    $dbProvider = \App\Models\NotificationProvider::where('channel', 'email')
        ->where('providerKey', 'brevo')
        ->where('isActive', true)
        ->first();

    $dbConfig = $dbProvider?->config ?? [];

    $this->apiKey    = $dbConfig['apiKey'] ?: config('services.brevo.key', '');
    $this->fromEmail = $dbConfig['fromEmail'] ?: config('services.brevo.from_email', '');
    $this->fromName  = $dbConfig['fromName'] ?: config('services.brevo.from_name', 'NCSR');

    if (empty($this->apiKey)) {
        Log::warning('BrevoService: BREVO_API_KEY is not configured.');
    }
}
    /**
     * Send a plain transactional email.
     */
    public function sendTransactional(
            string $to,
    string $name,
    string $subject,
    string $message,
): bool {
    if (empty($this->apiKey)) {
        Log::error('Brevo email skipped — API key not configured', [
            'to'      => $to,
            'subject' => $subject,
        ]);
        return false;
    }
        try {
            $response = Http::withHeaders([
                'api-key'      => $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post("{$this->apiUrl}/smtp/email", [
                'sender' => [
                    'email' => $this->fromEmail,
                    'name'  => $this->fromName,
                ],
                'to' => [
                    ['email' => $to, 'name' => $name],
                ],
                'subject'     => $subject,
                'htmlContent' => $this->wrapHtml($name, $message),
                'textContent' => $message,
            ]);

            if (!$response->successful()) {
                Log::error('Brevo email failed', [
                    'to'     => $to,
                    'status' => $response->status(),
                    'body'   => $response->json(),
                ]);
                return false;
            }

            Log::info('Brevo email sent', ['to' => $to, 'subject' => $subject]);
            return true;

        } catch (\Throwable $e) {
            Log::error('Brevo exception', [
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Wrap plain text in a simple branded HTML email.
     */
    protected function wrapHtml(string $name, string $message): string
    {
        $messageHtml = nl2br(htmlspecialchars($message));

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="margin:0;padding:0;background-color:#f4f4f4;font-family:Arial,sans-serif;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f4;padding:30px 0;">
                <tr>
                    <td align="center">
                        <table width="600" cellpadding="0" cellspacing="0"
                            style="background-color:#ffffff;border-radius:12px;overflow:hidden;
                                   box-shadow:0 2px 8px rgba(0,0,0,0.08);">

                            <!-- Header -->
                            <tr>
                                <td style="background-color:#166534;padding:28px 40px;">
                                    <p style="margin:0;color:#ffffff;font-size:20px;font-weight:bold;">
                                        National Cancer Screening Registry
                                    </p>
                                    <p style="margin:4px 0 0;color:#bbf7d0;font-size:13px;">
                                        NICRAT — Federal Ministry of Health
                                    </p>
                                </td>
                            </tr>

                            <!-- Body -->
                            <tr>
                                <td style="padding:36px 40px;">
                                    <p style="margin:0 0 16px;color:#374151;font-size:15px;">
                                        Dear {$name},
                                    </p>
                                    <p style="margin:0 0 24px;color:#374151;font-size:15px;line-height:1.7;">
                                        {$messageHtml}
                                    </p>
                                    <hr style="border:none;border-top:1px solid #e5e7eb;margin:28px 0;">
                                    <p style="margin:0;color:#9ca3af;font-size:12px;line-height:1.6;">
                                        This message was sent by the National Cancer Screening Registry (NCSR).<br>
                                        If you did not register for cancer screening, please ignore this message
                                        or contact us immediately.
                                    </p>
                                </td>
                            </tr>

                            <!-- Footer -->
                            <tr>
                                <td style="background-color:#f9fafb;padding:20px 40px;
                                           border-top:1px solid #e5e7eb;">
                                    <p style="margin:0;color:#9ca3af;font-size:12px;text-align:center;">
                                        © {$this->fromName} · National Institute for Cancer Research and Treatment
                                    </p>
                                </td>
                            </tr>

                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;
    }
}