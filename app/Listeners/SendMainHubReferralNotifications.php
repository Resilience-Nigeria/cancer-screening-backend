<?php
// app/Listeners/SendMainHubReferralNotifications.php

namespace App\Listeners;

use App\Events\ClientReferredToMainHub;
use App\Models\NotificationProvider;
use App\Services\BrevoService;
use App\Services\WhatsAppService;
use App\Services\BulkSmsService;
use App\Services\TwilioSmsService;
use Illuminate\Support\Facades\Log;

class SendMainHubReferralNotifications
{
    public function __construct(
        protected BrevoService $brevo,
        protected WhatsAppService $whatsapp,
        protected BulkSmsService $bulkSms,
        protected TwilioSmsService $twilioSms,
    ) {}

    protected function sendSms(string $to, string $message): bool
    {
        $provider = NotificationProvider::getDefault('sms');

        return match ($provider?->providerKey ?? 'bulksms') {
            'twilio' => $this->twilioSms->send($to, $message),
            'bulksms' => $this->bulkSms->send($to, $message),
            default => $this->bulkSms->send($to, $message),
        };
    }

    public function handle(ClientReferredToMainHub $event): void
    {
        $client     = $event->client;
        $toFacility = $event->toFacility;

        $clinicHours = $toFacility->formatClinicHours();

        // --- Notify client ---
        $clientMessage =
            "Hello {$client->fullName}, your screening result requires further "
            . "confirmation at a specialist centre.\n\n"
            . "Please visit: {$toFacility->facilityName}\n"
            . "Address: {$toFacility->facilityAddress}\n"
            . ($clinicHours ? "Clinic hours: {$clinicHours}\n" : "")
            . "Contact: {$toFacility->navigatorName} — {$toFacility->navigatorPhone}\n\n"
            . "Please attend as soon as possible.";

        $clientSms =
            "NCSR: Hello {$client->fullName}, your screening result requires further confirmation. "
            . "Please visit {$toFacility->facilityName}"
            . ($toFacility->facilityAddress ? ", {$toFacility->facilityAddress}" : "")
            . ($clinicHours ? ". Hours: {$clinicHours}" : "")
            . ". Contact: {$toFacility->navigatorName} {$toFacility->navigatorPhone}.";

        if ($client->email) {
            $this->brevo->sendTransactional(
                to: $client->email,
                name: $client->fullName,
                subject: 'Confirmation Screening — Next Steps',
                message: $clientMessage,
            );
        }

        if ($client->phoneNumber) {
            $sent = $this->whatsapp->send($client->phoneNumber, $clientMessage);
            Log::info('Client WhatsApp send result (main hub referral)', ['to' => $client->phoneNumber, 'result' => $sent]);

            $smsSent = $this->sendSms($client->phoneNumber, $clientSms);
            Log::info('Client SMS send result (main hub referral)', ['to' => $client->phoneNumber, 'result' => $smsSent]);
        }

        // --- Notify main hub navigator ---
        $navigatorMessage =
            "A client has been referred to your facility for confirmation screening.\n\n"
            . "Client: {$client->fullName}\n"
            . "Phone: {$client->phoneNumber}\n"
            . "Referred from: {$event->fromFacility->facilityName}";

        if ($toFacility->navigatorEmail) {
            $this->brevo->sendTransactional(
                to: $toFacility->navigatorEmail,
                name: $toFacility->navigatorName ?? 'Navigator',
                subject: 'New Referral — Confirmation Screening',
                message: $navigatorMessage,
            );
        }

        if ($toFacility->whatsappNumber) {
            $this->whatsapp->send($toFacility->whatsappNumber, $navigatorMessage);
        }

        // Mark referral notified
        $event->referral->update(['notifiedAt' => now()]);
    }
}
