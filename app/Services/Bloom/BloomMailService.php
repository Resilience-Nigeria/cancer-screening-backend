<?php

namespace App\Services\Bloom;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends Bloom's one-time codes by email through Brevo, using the same
 * BREVO_API_KEY / MAIL_FROM_ADDRESS / MAIL_FROM_NAME settings as BrevoMailService.
 */
class BloomMailService
{
    public function sendCode(string $email, ?string $name, string $code, string $purpose, int $ttlMinutes): bool
    {
        $apiKey = config('bloom.mail.api_key');
        $from   = config('bloom.mail.from_address') ?: config('mail.from.address');

        if (!$apiKey || !$from) {
            Log::error('Bloom email not sent: missing ' . implode(' and ', array_filter([
                $apiKey ? null : 'BREVO_API_KEY',
                $from ? null : 'MAIL_FROM_ADDRESS',
            ])) . '. If they are in .env, run: php artisan config:clear');
            return false;
        }

        $reset   = $purpose === BloomOtpService::PURPOSE_RESET;
        $subject = $reset ? 'Your Bloom password reset code' : 'Your Bloom verification code';
        $intro   = $reset ? 'Use this code to reset your Bloom password:' : 'Use this code to confirm your email and finish creating your Bloom account:';
        $hello   = e($name ? "Hi {$name}," : 'Hi,');
        $codeEsc = e($code);

        $html = <<<HTML
<div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;padding:24px;color:#1F1B3D">
  <h2 style="margin:0 0 16px;color:#7C5CE0">Bloom</h2>
  <p>{$hello}</p>
  <p>{$intro}</p>
  <p style="font-size:34px;letter-spacing:8px;font-weight:bold;margin:24px 0">{$codeEsc}</p>
  <p>It expires in {$ttlMinutes} minutes. If you didn't ask for this, you can ignore this email.</p>
  <p style="color:#777;font-size:12px">Never share this code with anyone.</p>
</div>
HTML;

        try {
            $response = Http::withHeaders(['api-key' => $apiKey, 'content-type' => 'application/json'])
                ->acceptJson()
                ->post('https://api.brevo.com/v3/smtp/email', [
                    'to'          => [['email' => $email, 'name' => $name ?: $email]],
                    'sender'      => ['name' => config('bloom.mail.from_name') ?: 'Bloom', 'email' => $from],
                    'subject'     => $subject,
                    'htmlContent' => $html,
                ]);

            if (!$response->successful()) {
                Log::error('Bloom email failed', ['status' => $response->status(), 'body' => $response->body()]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Bloom email exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /** A short plain message (e.g. "your health worker account is approved"). Paragraphs are escaped. */
    public function sendNotice(string $email, ?string $name, string $subject, array $paragraphs): bool
    {
        $apiKey = config('bloom.mail.api_key');
        $from   = config('bloom.mail.from_address') ?: config('mail.from.address');
        if (!$apiKey || !$from) {
            Log::error('Bloom email not sent: BREVO_API_KEY or MAIL_FROM_ADDRESS missing (php artisan config:clear if they are in .env)');
            return false;
        }

        $hello = e($name ? "Hi {$name}," : 'Hi,');
        $body  = implode('', array_map(fn ($p) => '<p>' . e($p) . '</p>', $paragraphs));
        $html  = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;padding:24px;color:#1F1B3D">'
            . '<h2 style="margin:0 0 16px;color:#159E96">Bloom</h2><p>' . $hello . '</p>' . $body . '</div>';

        try {
            $response = Http::withHeaders(['api-key' => $apiKey, 'content-type' => 'application/json'])
                ->acceptJson()
                ->post('https://api.brevo.com/v3/smtp/email', [
                    'to'          => [['email' => $email, 'name' => $name ?: $email]],
                    'sender'      => ['name' => config('bloom.mail.from_name') ?: 'Bloom', 'email' => $from],
                    'subject'     => $subject,
                    'htmlContent' => $html,
                ]);
            if (!$response->successful()) {
                Log::error('Bloom notice email failed', ['status' => $response->status(), 'body' => $response->body()]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Bloom notice email exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /** ada.obi@gmail.com => a***@gmail.com */
    public static function mask(string $email): string
    {
        [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return ($user === '' ? '' : mb_substr($user, 0, 1)) . '***@' . $domain;
    }
}
