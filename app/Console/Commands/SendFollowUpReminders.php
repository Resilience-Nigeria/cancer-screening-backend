<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\ScreeningSample;
use App\Models\SmsLog;
use App\Services\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Daily: SMS reminder the day before a repeat screening or a booked test.
 *   php artisan ncsr:followup-reminders            (send)
 *   php artisan ncsr:followup-reminders --dry-run  (list who would get one)
 */
class SendFollowUpReminders extends Command
{
    protected $signature = 'ncsr:followup-reminders {--dry-run}';
    protected $description = 'SMS clients the day before a repeat screening or a booked test';

    public function handle(): int
    {
        $tomorrow = Carbon::tomorrow();
        $date = $tomorrow->format('j M Y');
        $jobs = collect();

        if (class_exists(\App\Models\FollowUpSchedule::class)) {
            foreach (\App\Models\FollowUpSchedule::where('status', 'pending')->whereDate('dueDate', $tomorrow)->get() as $s) {
                $jobs->push(['clientId' => $s->clientId, 'purpose' => 'recall', 'facilityId' => null]);
            }
        }
        foreach (ScreeningSample::where('status', 'booked')->whereDate('sampleDate', $tomorrow)->get() as $s) {
            $jobs->push(['clientId' => $s->clientId, 'purpose' => 'booked_test', 'facilityId' => $s->bookedFacilityId ?? $s->facilityId]);
        }

        $sent = 0;
        foreach ($jobs->unique(fn ($j) => $j['clientId'] . $j['purpose']) as $j) {
            $c = Client::with('facility')->find($j['clientId']);
            if (!$c) continue;
            $already = SmsLog::where('clientId', $c->clientId)->where('purpose', $j['purpose'])->where('created_at', '>=', now()->subDay())->exists();
            if ($already) continue;
            $fac = $j['facilityId'] ? (\App\Models\Facility::find($j['facilityId'])->facilityName ?? null) : ($c->facility->facilityName ?? null);
            $text = SmsService::template($j['purpose'], ['name' => $c->fullName, 'facility' => $fac, 'date' => $date]);
            if ($this->option('dry-run')) {
                $this->line("{$c->clientId} {$c->phoneNumber}: {$text}");
                continue;
            }
            $r = SmsService::send($c->phoneNumber ?: ($c->caregiverPhoneNumber ?? null), $text, $j['purpose'], $c->clientId);
            if ($r['ok']) $sent++;
        }
        $this->info(($this->option('dry-run') ? 'Would send ' . $jobs->count() : "Sent {$sent}") . ' reminder(s).');
        return self::SUCCESS;
    }
}
