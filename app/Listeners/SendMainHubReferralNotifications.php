<?php
// app/Listeners/SendMainHubReferralNotifications.php

namespace App\Listeners;

use App\Events\ClientReferredToMainHub;
use App\Services\BrevoService;
use App\Services\WhatsAppService;

class SendMainHubReferralNotifications
{
    public function __construct(
        protected BrevoService $brevo,
        protected WhatsAppService $whatsapp,
    ) {}

    public function handle(ClientReferredToMainHub $event): void
    {
        $client     = $event->client;
        $toFacility = $event->toFacility;

        // --- Notify client ---
        $clientMessage =
            "Hello {$client->fullName}, your screening result requires further "
            . "confirmation at a specialist centre.\n\n"
            . "Please visit: {$toFacility->facilityName}\n"
            . "Address: {$toFacility->facilityAddress}\n"
            . "Contact: {$toFacility->navigatorName} — {$toFacility->navigatorPhone}\n\n"
            . "Please attend as soon as possible.";

        if ($client->email) {
            $this->brevo->sendTransactional(
                to: $client->email,
                name: $client->fullName,
                subject: 'Confirmation Screening — Next Steps',
                message: $clientMessage,
            );
        }

        if ($client->phoneNumber) {
            $this->whatsapp->send($client->phoneNumber, $clientMessage);
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