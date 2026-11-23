<?php

namespace App\Console\Commands;

use App\Models\ReportSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs every few minutes from the scheduler and sends each report when its day/time in Report Settings
 * has come (Africa/Lagos time). Changing the day or time on the settings page takes effect immediately;
 * nothing in routes/console.php needs editing.
 *
 *   php artisan ncsr:reports-dispatch          (what the scheduler runs)
 *   php artisan ncsr:reports-dispatch --status (show each report's next send time)
 */
class DispatchReports extends Command
{
    protected $signature = 'ncsr:reports-dispatch {--status}';
    protected $description = 'Send the scheduled NCSR report emails that are due';

    private const COMMANDS = [
        'weekly_summary'  => 'ncsr:weekly-summary',
        'overdue_results' => 'ncsr:overdue-results',
    ];

    public function handle(): int
    {
        foreach (self::COMMANDS as $key => $command) {
            $setting = ReportSetting::for($key);

            if ($this->option('status')) {
                $next = $setting->nextRunAt();
                $this->line(str_pad($key, 18) . ($setting->enabled ? 'on ' : 'off') . '  next: ' . ($next ? $next->format('D j M Y H:i') : '—')
                    . '  last: ' . ($setting->lastRunAt ? $setting->lastRunAt->timezone(ReportSetting::TIMEZONE)->format('D j M H:i') : 'never'));
                continue;
            }

            if (!$setting->isDue()) continue;

            // Mark it first so a slow send can't be started twice.
            $setting->forceFill(['lastRunAt' => now()])->save();
            $this->info("Sending {$key}…");
            try {
                Artisan::call($command, [], $this->output);
            } catch (\Throwable $e) {
                report($e);
                $this->error("{$key} failed: {$e->getMessage()}");
            }
        }
        return self::SUCCESS;
    }
}
