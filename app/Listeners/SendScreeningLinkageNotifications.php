<?php

namespace App\Listeners;

use App\Events\ClientLinkedToScreeningCenter;
use App\Services\BrevoService;
use App\Services\WhatsAppService;

class SendScreeningLinkageNotifications
{
    public function __construct(
        protected BrevoService $brevo,
        protected WhatsAppService $whatsapp,
    ) {}

    public function handle(ClientLinkedToScreeningCenter $event): void
    {
        $client = $event->client;
        $facility = $event->facility;

        // --- Notify client ---
        $clientMessage = "Hello {$client->fullName}, you have been linked to "
            . "{$facility->facilityName} for your cancer screening. "
            . "Address: {$facility->facilityAddress}. "
            . "Contact: {$facility->navigatorName} — {$facility->navigatorPhone}.";

        if ($client->email) {
            $this->brevo->sendTransactional(
                to: $client->email,
                name: $client->fullName,
                subject: 'Your Screening Center Details',
                message: $clientMessage,
            );
        }

        if ($client->phoneNumber) {
            $this->whatsapp->send($client->phoneNumber, $clientMessage);
        }

        // --- Notify navigator ---
        if ($facility->navigatorPhone || $facility->navigatorEmail) {
            $navigatorMessage = "A new client has been linked to your facility. "
                . "Name: {$client->fullName}. "
                . "Phone: {$client->phoneNumber}.";

            if ($facility->navigatorEmail) {
                $this->brevo->sendTransactional(
                    to: $facility->navigatorEmail,
                    name: $facility->navigatorName ?? 'Navigator',
                    subject: 'New Client Linked to Your Facility',
                    message: $navigatorMessage,
                );
            }

            if ($facility->whatsappNumber) {
                $this->whatsapp->send($facility->whatsappNumber, $navigatorMessage);
            }
        }
    }
}