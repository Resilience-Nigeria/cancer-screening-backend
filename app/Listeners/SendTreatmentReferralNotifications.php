<?php
// app/Listeners/SendTreatmentReferralNotifications.php

namespace App\Listeners;

use App\Events\ClientReferredToTreatment;
use App\Models\NotificationProvider;
use App\Services\BrevoService;
use App\Services\WhatsAppService;
use App\Services\BulkSmsService;
use App\Services\TwilioSmsService;
use Illuminate\Support\Facades\Log;

class SendTreatmentReferralNotifications
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

    public function handle(ClientReferredToTreatment $event): void
    {
        $client     = $event->client;
        $toFacility = $event->toFacility;

        $clinicHours = $toFacility->formatClinicHours();

        // --- Notify client ---
        $clientMessage =
            "Hello {$client->fullName}, your screening has been confirmed and "
            . "you have been referred for treatment.\n\n"
            . "Treatment Centre: {$toFacility->facilityName}\n"
            . "Address: {$toFacility->facilityAddress}\n"
            . ($clinicHours ? "Clinic hours: {$clinicHours}\n" : "")
            . "Contact: {$toFacility->navigatorName} — {$toFacility->navigatorPhone}\n\n"
            . "Please attend as soon as possible. Early treatment saves lives.";

        $clientSms =
            "NCSR: Hello {$client->fullName}, your screening has been confirmed and you have been "
            . "referred for treatment. Centre: {$toFacility->facilityName}"
            . ($toFacility->facilityAddress ? ", {$toFacility->facilityAddress}" : "")
            . ($clinicHours ? ". Hours: {$clinicHours}" : "")
            . ". Contact: {$toFacility->navigatorName} {$toFacility->navigatorPhone}. Please attend as soon as possible.";

        if ($client->email) {
            $this->brevo->sendTransactional(
                to: $client->email,
                name: $client->fullName,
                subject: 'Treatment Referral — Action Required',
                message: $clientMessage,
            );
        }

        if ($client->phoneNumber) {
            $sent = $this->whatsapp->send($client->phoneNumber, $clientMessage);
            Log::info('Client WhatsApp send result (treatment referral)', ['to' => $client->phoneNumber, 'result' => $sent]);

            $smsSent = $this->sendSms($client->phoneNumber, $clientSms);
            Log::info('Client SMS send result (treatment referral)', ['to' => $client->phoneNumber, 'result' => $smsSent]);
        }

        // --- Notify treatment navigator ---
        $navigatorMessage =
            "A client has been referred to your facility for treatment.\n\n"
            . "Client: {$client->fullName}\n"
            . "Phone: {$client->phoneNumber}\n"
            . "Referred from: {$event->fromFacility->facilityName}";

        if ($toFacility->navigatorEmail) {
            $this->brevo->sendTransactional(
                to: $toFacility->navigatorEmail,
                name: $toFacility->navigatorName ?? 'Navigator',
                subject: 'New Treatment Referral',
                message: $navigatorMessage,
            );
        }

        if ($toFacility->whatsappNumber) {
            $this->whatsapp->send($toFacility->whatsappNumber, $navigatorMessage);
        }

        $event->referral->update(['notifiedAt' => now()]);
    }
}
