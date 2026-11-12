<?php

namespace App\Console\Commands;

use App\Mail\OverdueResultsReminder;
use App\Support\BrevoApiMailer;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\OverdueResults;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Emails every active staff member the samples / tests waiting for a result for more than N days (default 7):
 * super / NICRAT admins get all facilities, hospital admins their facilities, everyone else their own facility.
 * Nobody with nothing overdue gets an email.
 *
 *   php artisan ncsr:overdue-results            (send)
 *   php artisan ncsr:overdue-results --dry-run  (list who would get what, send nothing)
 *   php artisan ncsr:overdue-results --pause=3  (seconds between emails; default 2, for mail plans with a rate limit)
 *   php artisan ncsr:overdue-results --via=smtp (use Laravel's mailer instead of the Brevo API)
 *   php artisan ncsr:overdue-results --only=you@example.com  (send only to this one user, for testing)
 */
class SendOverdueResultsReminders extends Command
{
    protected $signature = 'ncsr:overdue-results {--days=7} {--dry-run} {--pause=2 : Seconds to wait between emails} {--via=brevo : brevo (HTTP API, default) or smtp} {--only= : Send only to this email address}';
    protected $description = 'Email staff about screening test results that are overdue';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $link = rtrim((string) (config('app.frontend_url') ?: env('FRONTEND_URL', config('app.url'))), '/')
            . '/ncsr/screening-tests-page?tab=results';

        // Every active user with an email; each one only gets the facilities they are responsible for.
        $admins = User::with('user_role')->get()->filter(function ($u) {
            $status = strtolower((string) ($u->status ?? 'active'));
            return !empty($u->email) && !in_array($status, ['inactive', 'disabled', 'suspended'], true);
        });

        $only = strtolower(trim((string) $this->option('only')));
        if ($only !== '') {
            $admins = $admins->filter(fn ($u) => strtolower($u->email) === $only);
            if ($admins->isEmpty()) {
                $this->error("No active user with the email {$only}.");
                return self::FAILURE;
            }
        }

        $via = strtolower((string) $this->option('via')) === 'smtp' ? 'smtp' : 'brevo';
        if ($via === 'brevo' && !BrevoApiMailer::apiKey()) {
            $this->error('Brevo API key not found (services.brevo.api_key / BREVO_API_KEY). Use --via=smtp or set the key.');
            return self::FAILURE;
        }
        // Logos are linked from the backend's public folder when sending through the API.
        $logoBase = rtrim((string) config('app.url'), '/');

        $sent = 0;
        $failed = 0;
        $pause = max(0, (float) $this->option('pause'));
        $first = true;
        foreach ($admins as $admin) {
            $data = OverdueResults::forUser($admin, $days);
            if ($data['total'] === 0) continue;

            $name = trim(($admin->firstName ?? '') . ' ' . ($admin->lastName ?? '')) ?: $admin->email;
            $this->line("{$admin->email}: {$data['total']} overdue");

            if ($this->option('dry-run')) continue;

            // Space the emails out so mail services with a per-second limit don't reject them.
            if (!$first && $pause > 0) usleep((int) ($pause * 1_000_000));
            $first = false;

            // A rate-limit rejection is retried up to 3 times, waiting longer each time.
            $ok = false;
            for ($attempt = 1; $attempt <= 4 && !$ok; $attempt++) {
                try {
                    if ($via === 'brevo') {
                        $mailable = new OverdueResultsReminder($name, $data, $link);
                        $html = view('emails.overdue-results', [
                            'recipientName' => $name,
                            'data'          => $data,
                            'link'          => $link,
                            'logoBase'      => $logoBase,
                        ])->render();
                        BrevoApiMailer::send($admin->email, $name, $mailable->envelope()->subject, $html);
                    } else {
                        Mail::to($admin->email)->send(new OverdueResultsReminder($name, $data, $link));
                    }
                    $ok = true;
                    $sent++;
                } catch (\Throwable $e) {
                    $rateLimited = $e->getCode() === 429
                        || str_contains($e->getMessage(), 'Too many emails')
                        || str_contains($e->getMessage(), ' 550 ')
                        || str_contains(strtolower($e->getMessage()), 'rate');
                    if ($rateLimited && $attempt < 4) {
                        $wait = 5 * $attempt;
                        $this->warn("{$admin->email}: mail server busy, retrying in {$wait}s…");
                        sleep($wait);
                        continue;
                    }
                    report($e);
                    $failed++;
                    $this->error("Could not email {$admin->email}: {$e->getMessage()}");
                    break;
                }
            }
        }

        if ($sent > 0 && class_exists(ActivityLog::class)) {
            ActivityLog::record('overdue_results_reminder', "Overdue-result reminder emailed to {$sent} staff member(s).", [
                'details' => ['days' => $days, 'recipients' => $sent],
            ]);
        }

        $this->line("Sent through: " . ($via === 'brevo' ? 'Brevo API' : 'SMTP (' . config('mail.mailers.smtp.host') . ')'));
        $this->info($this->option('dry-run') ? 'Dry run: nothing sent.' : "Sent {$sent} reminder(s)" . ($failed ? ", {$failed} failed." : '.'));
        return self::SUCCESS;
    }
}
