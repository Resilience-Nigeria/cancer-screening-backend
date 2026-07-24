<?php
// app/Listeners/SendTreatmentReferralNotifications.php

namespace App\Listeners;

use App\Events\ClientReferredToTreatment;
use App\Services\BrevoService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;  

class SendTreatmentReferralNotifications
{
    public function __construct(
        protected BrevoService $brevo,
        protected WhatsAppService $whatsapp,
    ) {}

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

        if ($client->email) {
            $this->brevo->sendTransactional(
                to: $client->email,
                name: $client->fullName,
                subject: 'Treatment Referral — Action Required',
                message: $clientMessage,
            );
        }

        if ($client->phoneNumber) {
            $this->whatsapp->send($client->phoneNumber, $clientMessage);
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