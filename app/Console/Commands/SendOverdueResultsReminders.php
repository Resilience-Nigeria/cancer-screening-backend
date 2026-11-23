<?php

namespace App\Console\Commands;

use App\Mail\OverdueResultsReminder;
use App\Models\ActivityLog;
use App\Models\ReportSetting;
use App\Support\BrevoApiMailer;
use App\Support\OverdueResults;
use App\Support\ReportMailer;
use App\Support\ReportRecipients;
use Illuminate\Console\Command;

/**
 * Emails the samples / tests waiting for a result for more than N days to the people on the
 * overdue_results list (Report Settings). Each person gets only the facilities their entry covers.
 * Nobody with nothing overdue gets an email.
 *
 *   php artisan ncsr:overdue-results            (send)
 *   php artisan ncsr:overdue-results --dry-run  (list who would get what, send nothing)
 *   php artisan ncsr:overdue-results --days=10  (override the number of days set in Report Settings)
 *   php artisan ncsr:overdue-results --only=you@example.com
 *   php artisan ncsr:overdue-results --via=smtp
 */
class SendOverdueResultsReminders extends Command
{
    protected $signature = 'ncsr:overdue-results {--days= : Days before a result counts as overdue (default: Report Settings, 7)} {--dry-run} {--pause=2 : Seconds to wait between emails} {--via=brevo : brevo (HTTP API, default) or smtp} {--only= : Send only to this email address}';
    protected $description = 'Email staff about screening test results that are overdue';

    public function handle(): int
    {
        $setting = ReportSetting::for('overdue_results');
        $days = max(1, (int) ($this->option('days') ?: $setting->option('days', OverdueResults::DEFAULT_DAYS)));
        $link = ReportMailer::frontendUrl('/ncsr/screening-tests-page?tab=results');

        $recipients = ReportRecipients::resolve('overdue_results');
        $only = strtolower(trim((string) $this->option('only')));
        if ($only !== '') {
            $recipients = array_filter($recipients, fn ($r, $k) => $k === $only, ARRAY_FILTER_USE_BOTH);
            if (!$recipients) {
                $this->error("{$only} is not on the overdue reminder list (Report Settings).");
                return self::FAILURE;
            }
        }
        if (!$recipients) {
            $this->warn('Nobody is on the overdue reminder list. Add recipients under Report Settings.');
            return self::SUCCESS;
        }

        $via = strtolower((string) $this->option('via')) === 'smtp' ? 'smtp' : 'brevo';
        if ($via === 'brevo' && !BrevoApiMailer::apiKey() && !$this->option('dry-run')) {
            $this->error('Brevo API key not found (services.brevo.api_key / BREVO_API_KEY). Use --via=smtp or set the key.');
            return self::FAILURE;
        }

        $cache = [];
        $sent = 0;
        $failed = 0;
        $pause = max(0, (float) $this->option('pause'));
        $first = true;

        foreach ($recipients as $r) {
            $key = $r['facilities'] === null ? 'all' : implode(',', collect($r['facilities'])->sort()->all());
            $scope = $r['facilities'] === null ? 'all' : (count($r['facilities']) === 1 ? 'facility' : 'facilities');
            $cache[$key] ??= OverdueResults::forFacilities($r['facilities'], $scope, $days);
            $data = $cache[$key];
            if ($data['total'] === 0) continue;

            $this->line("{$r['email']}: {$data['total']} overdue");
            if ($this->option('dry-run')) continue;

            if (!$first && $pause > 0) usleep((int) ($pause * 1_000_000));
            $first = false;

            $subject = (new OverdueResultsReminder($r['name'], $data, $link))->envelope()->subject;
            $html = view('emails.overdue-results', [
                'recipientName' => $r['name'],
                'data' => $data,
                'link' => $link,
                'logoBase' => rtrim((string) config('app.url'), '/'),
            ])->render();

            $res = ReportMailer::send($r['email'], $r['name'], $subject, $html, $via, fn ($w) => $this->warn("{$r['email']}: mail server busy, retrying in {$w}s…"));
            if ($res === true) {
                $sent++;
            } else {
                $failed++;
                $this->error("Could not email {$r['email']}: {$res}");
            }
        }

        if ($sent > 0 && class_exists(ActivityLog::class)) {
            ActivityLog::record('overdue_results_reminder', "Overdue-result reminder emailed to {$sent} recipient(s).", [
                'details' => ['days' => $days, 'recipients' => $sent, 'failed' => $failed],
            ]);
        }

        $this->line('Sent through: ' . ($via === 'brevo' ? 'Brevo API' : 'SMTP (' . config('mail.mailers.smtp.host') . ')'));
        $this->info($this->option('dry-run') ? 'Dry run: nothing sent.' : "Sent {$sent} reminder(s)" . ($failed ? ", {$failed} failed." : '.'));
        return self::SUCCESS;
    }
}
