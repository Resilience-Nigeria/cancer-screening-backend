<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends an HTML email through Brevo's HTTP API (https://api.brevo.com/v3/smtp/email), the same route the
 * sign-up / welcome email uses. It goes out over HTTPS (port 443), so it is not affected by the SMTP ports
 * DigitalOcean blocks.
 *
 * API key: the first one found of config('services.brevo.api_key'), config('services.brevo.key'),
 * env('BREVO_API_KEY'). Sender: MAIL_FROM_ADDRESS / MAIL_FROM_NAME (must be a sender verified in Brevo).
 */
class BrevoApiMailer
{
    public const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    public static function apiKey(): ?string
    {
        return config('services.brevo.api_key')
            ?: config('services.brevo.key')
            ?: env('BREVO_API_KEY');
    }

    /**
     * @return string|null Brevo messageId
     * @throws RuntimeException with the HTTP status in the code (429 = rate limited)
     */
    public static function send(string $toEmail, ?string $toName, string $subject, string $html): ?string
    {
        $key = self::apiKey();
        if (!$key) {
            throw new RuntimeException('Brevo API key not set (services.brevo.api_key / BREVO_API_KEY).');
        }

        $response = Http::withHeaders([
                'api-key' => $key,
                'accept'  => 'application/json',
            ])
            ->timeout(30)
            ->post(self::ENDPOINT, [
                'sender' => [
                    'email' => config('mail.from.address'),
                    'name'  => config('mail.from.name') ?: 'NCSR',
                ],
                'to' => [[
                    'email' => $toEmail,
                    'name'  => $toName ?: $toEmail,
                ]],
                'subject'     => $subject,
                'htmlContent' => $html,
            ]);

        if ($response->failed()) {
            $msg = $response->json('message') ?: $response->body();
            throw new RuntimeException("Brevo API {$response->status()}: {$msg}", $response->status());
        }

        return $response->json('messageId');
    }
}
