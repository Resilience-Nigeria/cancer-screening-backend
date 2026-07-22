<?php

namespace App\Console\Commands;

use App\Models\FollowUpSchedule;
use App\Models\Setting;
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
     * Resolves the configured SMS provider to its service instance.
     * Adding a new provider later is a case here, not a rewire of every
     * caller that sends an SMS.
     */
    protected function sendSms(string $to, string $message): bool
    {
        return match (Setting::get('sms_provider', 'bulksms')) {
            'bulksms' => $this->bulkSms->send($to, $message),
            default => $this->bulkSms->send($to, $message),
        };
    }

    /**
     * Same pattern for email.
     */
    protected function sendEmail(string $to, string $name, string $subject, string $message): bool
    {
        return match (Setting::get('email_provider', 'brevo')) {
            'brevo' => $this->brevo->sendTransactional(to: $to, name: $name, subject: $subject, message: $message),
            default => $this->brevo->sendTransactional(to: $to, name: $name, subject: $subject, message: $message),
        };
    }

    /**
     * Remind clients whose follow-up is due within the configured
     * window and hasn't already had a reminder sent.
     */
    protected function sendUpcomingReminders(): void
    {
        $daysBefore = Setting::get('follow_up_reminder_days_before', 7);
        $whatsappEnabled = Setting::get('whatsapp_enabled', true);

        $upcoming = FollowUpSchedule::with('treatmentPlan.client')
            ->where('status', 'pending')
            ->whereNull('reminderSentAt')
            ->whereBetween('dueDate', [now()->toDateString(), now()->addDays($daysBefore)->toDateString()])
            ->get();

        foreach ($upcoming as $schedule) {
            $client = $schedule->treatmentPlan?->client;
            if (!$client || !$client->phoneNumber) {
                continue;
            }

            $message = "Hello {$client->fullName}, this is a reminder that your follow-up appointment "
                . "({$schedule->activities}) is due on {$schedule->dueDate}. Please contact your facility to schedule your visit.";

            $sent = $whatsappEnabled ? $this->whatsapp->send($client->phoneNumber, $message) : false;
            if (!$sent) {
                $sent = $this->sendSms($client->phoneNumber, $message);
            }
            if (!$sent && $client->email) {
                $this->sendEmail($client->email, $client->fullName, 'Upcoming Follow-up Appointment', $message);
            }

            $schedule->update(['reminderSentAt' => now()]);

            Log::info('Follow-up reminder sent', ['scheduleId' => $schedule->scheduleId, 'clientId' => $client->clientId]);
        }

        $this->info("Sent {$upcoming->count()} follow-up reminders.");
    }

    /**
     * Anything more than the configured window past due and still
     * pending gets flagged as missed.
     */
    protected function flagMissedFollowUps(): void
    {
        $missedAfterDays = Setting::get('follow_up_missed_after_days', 14);

        $missedCount = FollowUpSchedule::where('status', 'pending')
            ->where('dueDate', '<', now()->subDays($missedAfterDays)->toDateString())
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

                $this->sendEmail($facility->email, $facility->facilityName, 'Overdue Follow-up Escalation', $message);
            }

            $schedule->update(['escalationSentAt' => now()]);
        }

        $this->info("Escalated {$overdue->count()} overdue follow-ups.");
    }
}
