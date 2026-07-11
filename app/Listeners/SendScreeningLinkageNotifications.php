<?php

namespace App\Listeners;

use App\Events\ClientLinkedToScreeningCenter;
use App\Services\BrevoService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;  

class SendScreeningLinkageNotifications
{
    public function __construct(
        protected BrevoService $brevo,
        protected WhatsAppService $whatsapp,
    ) {}

    public function handle(ClientLinkedToScreeningCenter $event): void
{
    $client     = $event->client;
    $facility   = $event->facility;

    Log::info('SendScreeningLinkageNotifications fired', [
        'client'      => $client->fullName ?? 'unknown',
        'phone'       => $client->phoneNumber ?? 'MISSING',
        'facility'    => $facility->facilityName ?? 'unknown',
        'whatsapp_no' => $facility->whatsappNumber ?? 'MISSING',
    ]);

    $clientMessage =
        "Hello {$client->fullName}, you have been linked to "
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

    // Guard: skip if no phone
    if (!empty($client->phoneNumber)) {
        $result = $this->whatsapp->send($client->phoneNumber, $clientMessage);
        Log::info('WhatsApp client send result', ['result' => $result]);
    } else {
        Log::warning('WhatsApp skipped — client has no phone number');
    }

    // Navigator
    $navigatorMessage =
        "A new client has been linked to your facility. "
        . "Name: {$client->fullName}. "
        . "Phone: {$client->phoneNumber}.";

    if (!empty($facility->navigatorEmail)) {
        $this->brevo->sendTransactional(
            to: $facility->navigatorEmail,
            name: $facility->navigatorName ?? 'Navigator',
            subject: 'New Client Linked to Your Facility',
            message: $navigatorMessage,
        );
    }

    if (!empty($facility->whatsappNumber)) {
        $result = $this->whatsapp->send($facility->whatsappNumber, $navigatorMessage);
        Log::info('WhatsApp navigator send result', ['result' => $result]);
    } else {
        Log::warning('WhatsApp skipped — facility has no whatsappNumber');
    }
}
}