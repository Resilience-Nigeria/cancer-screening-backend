<?php

namespace App\Support;

use Illuminate\Support\Facades\Mail;

/**
 * Sends one report email through the Brevo HTTP API (default) or Laravel's mailer, retrying when the
 * mail service says it is busy (rate limit).
 */
class ReportMailer
{
    /** @return true|string true when sent, otherwise the error message */
    public static function send(string $email, ?string $name, string $subject, string $html, string $via = 'brevo', ?callable $onRetry = null)
    {
        for ($attempt = 1; $attempt <= 4; $attempt++) {
            try {
                if ($via === 'smtp') {
                    Mail::html($html, function ($m) use ($email, $name, $subject) {
                        $m->to($email, $name)->subject($subject);
                    });
                } else {
                    BrevoApiMailer::send($email, $name, $subject, $html);
                }
                return true;
            } catch (\Throwable $e) {
                $rateLimited = $e->getCode() === 429
                    || str_contains($e->getMessage(), 'Too many emails')
                    || str_contains(strtolower($e->getMessage()), 'rate');
                if ($rateLimited && $attempt < 4) {
                    if ($onRetry) $onRetry(5 * $attempt);
                    sleep(5 * $attempt);
                    continue;
                }
                report($e);
                return $e->getMessage();
            }
        }
        return 'Gave up after several retries.';
    }

    public static function frontendUrl(string $path): string
    {
        return rtrim((string) (config('app.frontend_url') ?: env('FRONTEND_URL', config('app.url'))), '/') . $path;
    }
}
