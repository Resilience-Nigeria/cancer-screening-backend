<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Support\BrevoApiMailer;
use App\Support\ReportMailer;
use App\Support\ReportRecipients;
use App\Support\WeeklySummary;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Emails the weekly management summary to everyone on the weekly_summary list (Report Settings).
 * Each person gets the figures for the facilities their entry covers.
 *
 *   php artisan ncsr:weekly-summary                       (send now, last 7 days)
 *   php artisan ncsr:weekly-summary --dry-run             (show who would get it)
 *   php artisan ncsr:weekly-summary --only=you@example.com
 *   php artisan ncsr:weekly-summary --from=2026-11-16 --to=2026-11-22
 */
class SendWeeklySummary extends Command
{
    protected $signature = 'ncsr:weekly-summary {--dry-run} {--only= : Send only to this email address} {--from=} {--to=} {--pause=2 : Seconds between emails} {--via=brevo : brevo or smtp}';
    protected $description = 'Email the weekly NCSR summary to management';

    public function handle(): int
    {
        [$from, $to] = WeeklySummary::defaultPeriod();
        if ($this->option('from')) $from = Carbon::parse($this->option('from'));
        if ($this->option('to')) $to = Carbon::parse($this->option('to'));

        $recipients = ReportRecipients::resolve('weekly_summary');
        $only = strtolower(trim((string) $this->option('only')));
        if ($only !== '') {
            $recipients = array_filter($recipients, fn ($r, $k) => $k === $only, ARRAY_FILTER_USE_BOTH);
            if (!$recipients) {
                $this->error("{$only} is not on the weekly summary list (Report Settings).");
                return self::FAILURE;
            }
        }
        if (!$recipients) {
            $this->warn('Nobody is on the weekly summary list. Add recipients under Report Settings.');
            return self::SUCCESS;
        }

        $via = strtolower((string) $this->option('via')) === 'smtp' ? 'smtp' : 'brevo';
        if ($via === 'brevo' && !BrevoApiMailer::apiKey() && !$this->option('dry-run')) {
            $this->error('Brevo API key not found (services.brevo.api_key / BREVO_API_KEY). Use --via=smtp or set the key.');
            return self::FAILURE;
        }

        $link = ReportMailer::frontendUrl('/ncsr/analytics');
        $names = DB::table('facilities')->pluck('facilityName', 'facilityId');
        $cache = [];
        $sent = 0;
        $failed = 0;
        $pause = max(0, (float) $this->option('pause'));
        $first = true;

        foreach ($recipients as $r) {
            $key = $r['facilities'] === null ? 'all' : implode(',', collect($r['facilities'])->sort()->all());
            $cache[$key] ??= WeeklySummary::build($r['facilities'], $from->copy(), $to->copy());
            $summary = $cache[$key];
            $scopeText = ReportRecipients::scopeText($r['facilities'], $names);

            $this->line("{$r['email']}: {$scopeText}");
            if ($this->option('dry-run')) continue;

            if (!$first && $pause > 0) usleep((int) ($pause * 1_000_000));
            $first = false;

            $html = view('emails.weekly-summary', [
                'recipientName' => $r['name'],
                'summary' => $summary,
                'scopeText' => $scopeText,
                'link' => $link,
            ])->render();
            $subject = "NCSR weekly summary · {$summary['period']['label']}";

            $res = ReportMailer::send($r['email'], $r['name'], $subject, $html, $via, fn ($w) => $this->warn("{$r['email']}: mail server busy, retrying in {$w}s…"));
            if ($res === true) {
                $sent++;
            } else {
                $failed++;
                $this->error("Could not email {$r['email']}: {$res}");
            }
        }

        if ($sent > 0 && class_exists(ActivityLog::class)) {
            ActivityLog::record('weekly_summary_sent', "Weekly summary ({$from->format('j M')} – {$to->format('j M Y')}) emailed to {$sent} recipient(s).", [
                'details' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'recipients' => $sent, 'failed' => $failed],
            ]);
        }

        $this->info($this->option('dry-run') ? 'Dry run: nothing sent.' : "Sent {$sent} summary email(s)" . ($failed ? ", {$failed} failed." : '.'));
        return self::SUCCESS;
    }
}
