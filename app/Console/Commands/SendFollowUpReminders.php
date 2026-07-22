<?php

namespace App\Console\Commands;

use App\Models\FollowUpSchedule;
use App\Services\BrevoService;
use App\Services\BulkSmsService;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendFollowUpReminders extends Command
{
    protected $signature = 'followups:send-reminders';
    protected $description = 'Send reminders for upcoming follow-ups, flag missed ones, and escalate overdue reviews (Stage 4, Section 4.11)';

    public function __construct(
        protected WhatsAppService $whatsapp,
        protected BulkSmsService $bulkSms,
        protected BrevoService $brevo,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->sendUpcomingReminders();
        $this->flagMissedFollowUps();
        $this->escalateOverdue();

        return self::SUCCESS;
    }

    /**
     * Remind clients whose follow-up is due in the next 7 days and
     * hasn't already had a reminder sent.
     */
    protected function sendUpcomingReminders(): void
    {
        $upcoming = FollowUpSchedule::with('treatmentPlan.client')
            ->where('status', 'pending')
            ->whereNull('reminderSentAt')
            ->whereBetween('dueDate', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->get();

        foreach ($upcoming as $schedule) {
            $client = $schedule->treatmentPlan?->client;
            if (!$client || !$client->phoneNumber) {
                continue;
            }

            $message = "Hello {$client->fullName}, this is a reminder that your follow-up appointment "
                . "({$schedule->activities}) is due on {$schedule->dueDate}. Please contact your facility to schedule your visit.";

            $sent = $this->whatsapp->send($client->phoneNumber, $message);
            if (!$sent) {
                $sent = $this->bulkSms->send($client->phoneNumber, $message);
            }
            if (!$sent && $client->email) {
                $this->brevo->sendTransactional(
                    to: $client->email,
                    name: $client->fullName,
                    subject: 'Upcoming Follow-up Appointment',
                    message: $message,
                );
            }

            $schedule->update(['reminderSentAt' => now()]);

            Log::info('Follow-up reminder sent', ['scheduleId' => $schedule->scheduleId, 'clientId' => $client->clientId]);
        }

        $this->info("Sent {$upcoming->count()} follow-up reminders.");
    }

    /**
     * Anything more than 14 days past due and still pending gets
     * flagged as missed.
     */
    protected function flagMissedFollowUps(): void
    {
        $missedCount = FollowUpSchedule::where('status', 'pending')
            ->where('dueDate', '<', now()->subDays(14)->toDateString())
            ->update(['status' => 'missed']);

        $this->info("Flagged {$missedCount} follow-ups as missed.");
    }

    /**
     * Missed follow-ups that haven't already had an escalation sent get
     * one — a stronger notice, distinct from the routine reminder,
     * covering the "escalation alerts for overdue reviews or incomplete
     * treatment" requirement.
     */
    protected function escalateOverdue(): void
    {
        $overdue = FollowUpSchedule::with('treatmentPlan.client', 'treatmentPlan.facility')
            ->where('status', 'missed')
            ->whereNull('escalationSentAt')
            ->get();

        foreach ($overdue as $schedule) {
            $client = $schedule->treatmentPlan?->client;
            $facility = $schedule->treatmentPlan?->facility;

            if ($facility && $facility->email) {
                $message = "URGENT: {$client?->fullName} ({$client?->clientId}) has a missed follow-up "
                    . "that was due on {$schedule->dueDate} ({$schedule->activities}). Please follow up urgently.";

                $this->brevo->sendTransactional(
                    to: $facility->email,
                    name: $facility->facilityName,
                    subject: 'Overdue Follow-up Escalation',
                    message: $message,
                );
            }

            $schedule->update(['escalationSentAt' => now()]);
        }

        $this->info("Escalated {$overdue->count()} overdue follow-ups.");
    }
}
