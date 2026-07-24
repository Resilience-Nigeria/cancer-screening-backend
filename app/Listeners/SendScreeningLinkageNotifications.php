<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
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
        $client   = $event->client;
        $facility = $event->facility;

        Log::info('SendScreeningLinkageNotifications fired', [
            'client'      => $client->fullName ?? 'unknown',
            'phone'       => $client->phoneNumber ?? 'MISSING',
            'facility'    => $facility->facilityName ?? 'unknown',
            'whatsapp_no' => $facility->whatsappNumber ?? 'MISSING',
        ]);

        $clinicHours = $facility->formatClinicHours();

        // ── Client email message ──────────────────────────────────────────
        $clientEmail =
            "Dear {$client->fullName},\n\n"
            . "Congratulations! Your cancer screening registration has been confirmed "
            . "and you have been linked to a screening centre near you.\n\n"
            . "SCREENING CENTRE DETAILS\n"
            . "------------------------\n"
            . "Centre: {$facility->facilityName}\n"
            . ($facility->facilityAddress
                ? "Address: {$facility->facilityAddress}\n"
                : "")
            . ($facility->facilityLga && $facility->facilityState
                ? "Location: {$facility->facilityLga}, {$facility->facilityState}\n"
                : "")
            . ($clinicHours
                ? "Clinic Hours: {$clinicHours}\n"
                : "")
            . "\n"
            . "YOUR NAVIGATOR (CONTACT PERSON)\n"
            . "--------------------------------\n"
            . ($facility->navigatorName
                ? "Name: {$facility->navigatorName}\n"
                : "")
            . ($facility->navigatorPhone
                ? "Phone: {$facility->navigatorPhone}\n"
                : "")
            . ($facility->navigatorEmail
                ? "Email: {$facility->navigatorEmail}\n"
                : "")
            . "\n"
            . "WHAT TO DO NEXT\n"
            . "---------------\n"
            . "1. Visit the screening centre as soon as possible.\n"
            . "2. Mention your name and that you registered through the NCSR.\n"
            . "3. Bring a valid ID if available.\n"
            . "4. Contact your navigator if you need directions or have questions.\n\n"
            . "Remember: Early detection saves lives. Please do not delay your screening.\n\n"
            . "This message was sent by the National Cancer Screening Registry (NCSR), "
            . "National Institute for Cancer Research and Treatment (NICRAT).";

        // ── Client email message ──────────────────────────────────────────
        $clientEmail =
            "Dear {$client->fullName},\n\n"
            . "Congratulations! Your cancer screening registration has been confirmed "
            . "and you have been linked to a screening centre near you.\n\n"
            . "SCREENING CENTRE DETAILS\n"
            . "------------------------\n"
            . "Centre: {$facility->facilityName}\n"
            . ($facility->facilityAddress
                ? "Address: {$facility->facilityAddress}\n"
                : "")
            . ($facility->facilityLga && $facility->facilityState
                ? "Location: {$facility->facilityLga}, {$facility->facilityState}\n"
                : "")
            . "\n"
            . "YOUR NAVIGATOR (CONTACT PERSON)\n"
            . "--------------------------------\n"
            . ($facility->navigatorName
                ? "Name: {$facility->navigatorName}\n"
                : "")
            . ($facility->navigatorPhone
                ? "Phone: {$facility->navigatorPhone}\n"
                : "")
            . ($facility->navigatorEmail
                ? "Email: {$facility->navigatorEmail}\n"
                : "")
            . "\n"
            . "WHAT TO DO NEXT\n"
            . "---------------\n"
            . "1. Visit the screening centre as soon as possible.\n"
            . "2. Mention your name and that you registered through the NCSR.\n"
            . "3. Bring a valid ID if available.\n"
            . "4. Contact your navigator if you need directions or have questions.\n\n"
            . "Remember: Early detection saves lives. Please do not delay your screening.\n\n"
            . "This message was sent by the National Cancer Screening Registry (NCSR), "
            . "National Institute for Cancer Research and Treatment (NICRAT).";

        // ── Send to client ────────────────────────────────────────────────
        if (!empty($client->email)) {
            $sent = $this->brevo->sendTransactional(
                to:      $client->email,
                name:    $client->fullName,
                subject: "Your Screening Centre — {$facility->facilityName}",
                message: $clientEmail,
            );
            Log::info('Client email send result', [
                'to'     => $client->email,
                'result' => $sent,
            ]);
        }

        if (!empty($client->phoneNumber)) {
            $sent = $this->whatsapp->send($client->phoneNumber, $clientWhatsApp);
            Log::info('Client WhatsApp send result', [
                'to'     => $client->phoneNumber,
                'result' => $sent,
            ]);
        }

        // ── Navigator WhatsApp message ────────────────────────────────────
        $navigatorWhatsApp =
            "Hello {$facility->navigatorName},\n\n"
            . "🔔 *A new client has been linked to your facility.*\n\n"
            . "👤 *Client Details*\n"
            . "*Name:* {$client->fullName}\n"
            . "*Phone:* {$client->phoneNumber}\n"
            . ($client->email
                ? "*Email:* {$client->email}\n"
                : "")
            . "\n"
            . "Please reach out to the client to schedule their screening appointment.\n\n"
            . "_National Cancer Screening Registry (NCSR) — NICRAT_";

        // ── Navigator email message ───────────────────────────────────────
        $navigatorEmail =
            "Dear {$facility->navigatorName},\n\n"
            . "A new client has been linked to your facility through the "
            . "National Cancer Screening Registry.\n\n"
            . "CLIENT DETAILS\n"
            . "--------------\n"
            . "Name:  {$client->fullName}\n"
            . "Phone: {$client->phoneNumber}\n"
            . ($client->email ? "Email: {$client->email}\n" : "")
            . "\n"
            . "Please contact this client as soon as possible to schedule "
            . "their cancer screening appointment at {$facility->facilityName}.\n\n"
            . "National Cancer Screening Registry (NCSR) — NICRAT";

        // ── Send to navigator ─────────────────────────────────────────────
        if (!empty($facility->navigatorEmail)) {
            $sent = $this->brevo->sendTransactional(
                to:      $facility->navigatorEmail,
                name:    $facility->navigatorName ?? 'Navigator',
                subject: "New Client Linked — {$client->fullName}",
                message: $navigatorEmail,
            );
            Log::info('Navigator email send result', [
                'to'     => $facility->navigatorEmail,
                'result' => $sent,
            ]);
        }

        if (!empty($facility->whatsappNumber)) {
            $sent = $this->whatsapp->send($facility->whatsappNumber, $navigatorWhatsApp);
            Log::info('Navigator WhatsApp send result', [
                'to'     => $facility->whatsappNumber,
                'result' => $sent,
            ]);
        }
    }
}
