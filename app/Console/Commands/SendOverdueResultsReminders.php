<?php

namespace App\Console\Commands;

use App\Mail\OverdueResultsReminder;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\OverdueResults;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Emails each admin a list of samples / tests in their facilities that have been waiting for a result
 * for more than N days (default 7). Nothing is sent to an admin with nothing overdue.
 *
 *   php artisan ncsr:overdue-results            (send)
 *   php artisan ncsr:overdue-results --dry-run  (list who would get what, send nothing)
 */
class SendOverdueResultsReminders extends Command
{
    protected $signature = 'ncsr:overdue-results {--days=7} {--dry-run}';
    protected $description = 'Email admins about screening test results that are overdue';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $link = rtrim((string) (config('app.frontend_url') ?: env('FRONTEND_URL', config('app.url'))), '/')
            . '/ncsr/screening-tests-page?tab=results';

        $admins = User::with('user_role')->get()->filter(function ($u) {
            $status = strtolower((string) ($u->status ?? 'active'));
            return OverdueResults::isAdmin($u) && !empty($u->email) && !in_array($status, ['inactive', 'disabled', 'suspended'], true);
        });

        $sent = 0;
        foreach ($admins as $admin) {
            $data = OverdueResults::forUser($admin, $days);
            if ($data['total'] === 0) continue;

            $name = trim(($admin->firstName ?? '') . ' ' . ($admin->lastName ?? '')) ?: $admin->email;
            $this->line("{$admin->email}: {$data['total']} overdue");

            if ($this->option('dry-run')) continue;

            try {
                Mail::to($admin->email)->send(new OverdueResultsReminder($name, $data, $link));
                $sent++;
            } catch (\Throwable $e) {
                report($e);
                $this->error("Could not email {$admin->email}: {$e->getMessage()}");
            }
        }

        if ($sent > 0 && class_exists(ActivityLog::class)) {
            ActivityLog::record('overdue_results_reminder', "Overdue-result reminder emailed to {$sent} admin(s).", [
                'details' => ['days' => $days, 'recipients' => $sent],
            ]);
        }

        $this->info($this->option('dry-run') ? 'Dry run: nothing sent.' : "Sent {$sent} reminder(s).");
        return self::SUCCESS;
    }
}
