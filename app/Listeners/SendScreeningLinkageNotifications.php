<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use App\Events\ClientLinkedToScreeningCenter;
use App\Models\NotificationProvider;
use App\Services\BrevoService;
use App\Services\WhatsAppService;
use App\Services\BulkSmsService;
use App\Services\TwilioSmsService;

class SendScreeningLinkageNotifications
{
    public function __construct(
        protected BrevoService $brevo,
        protected WhatsAppService $whatsapp,
        protected BulkSmsService $bulkSms,
        protected TwilioSmsService $twilioSms,
    ) {}

    /**
     * Resolves the configured default SMS provider - adding a new
     * provider later is a case here, not a rewire of every caller.
     * Same pattern used by OtpService and SendFollowUpReminders.
     */
    protected function sendSms(string $to, string $message): bool
    {
        $provider = NotificationProvider::getDefault('sms');

        return match ($provider?->providerKey ?? 'bulksms') {
            'twilio' => $this->twilioSms->send($to, $message),
            'bulksms' => $this->bulkSms->send($to, $message),
            default => $this->bulkSms->send($to, $message),
        };
    }

    public function handle(ClientLinkedToScreeningCenter $event): void
{
    $client    = $event->client;
    $facility  = $event->facility;
    $navigator = $event->navigator;

    // Prefer the actually-assigned navigator (from the navigators table);
    // fall back to the facility's static fields only if none is assigned.
    $navigatorName = $navigator?->user
        ? trim(implode(' ', array_filter([
            $navigator->user->firstName,
            $navigator->user->lastName,
            $navigator->user->otherNames,
          ])))
        : null;
    $navigatorName  = $navigatorName ?: ($facility->navigatorName ?? null);
    $navigatorPhone = $navigator?->user?->phoneNumber ?? $facility->navigatorPhone ?? null;
    $navigatorEmail = $navigator?->user?->email ?? $facility->navigatorEmail ?? null;

    Log::info('SendScreeningLinkageNotifications fired', [
        'client'          => $client->fullName ?? 'unknown',
        'phone'           => $client->phoneNumber ?? 'MISSING',
        'facility'        => $facility->facilityName ?? 'unknown',
        'navigator'       => $navigatorName ?? 'MISSING',
        'navigator_phone' => $navigatorPhone ?? 'MISSING',
    ]);

    $clinicHours = $facility->formatClinicHours();

    // ── Client WhatsApp message ───────────────────────────────────────
    $clientWhatsApp =
        "Hello {$client->fullName},\n\n"
        . "✅ *Your cancer screening registration is confirmed!*\n\n"
        . "You have been linked to a screening centre near you. "
        . "Please visit them as soon as possible — early detection saves lives.\n\n"
        . "🏥 *Your Screening Centre*\n"
        . "*{$facility->facilityName}*\n"
        . ($facility->facilityAddress ? "📍 {$facility->facilityAddress}\n" : "")
        . ($facility->facilityState ? "🗺️ {$facility->facilityLga}, {$facility->facilityState}\n" : "")
        . ($clinicHours ? "🕐 {$clinicHours}\n" : "")
        . "\n"
        . "👤 *Your Contact Person (Navigator)*\n"
        . ($navigatorName ? "*{$navigatorName}*\n" : "")
        . ($navigatorPhone ? "📞 {$navigatorPhone}\n" : "")
        . ($navigatorEmail ? "✉️ {$navigatorEmail}\n" : "")
        . "\n"
        . "Please mention this message when you arrive. "
        . "If you need to reschedule or have questions, contact your navigator directly.\n\n"
        . "_This message was sent by the National Cancer Screening Registry (NCSR) — NICRAT_";

    // ── Client SMS message ─────────────────────────────────────────────
    $clientSms =
        "NCSR: Hello {$client->fullName}, your cancer screening registration is confirmed. "
        . "Centre: {$facility->facilityName}"
        . ($facility->facilityAddress ? ", {$facility->facilityAddress}" : "")
        . ($clinicHours ? ". Hours: {$clinicHours}" : "")
        . ". Contact: {$navigatorName} {$navigatorPhone}. "
        . "Please visit as soon as possible.";

    // ── Client email message ──────────────────────────────────────────
    $clientEmail =
        "Dear {$client->fullName},\n\n"
        . "Congratulations! Your cancer screening registration has been confirmed "
        . "and you have been linked to a screening centre near you.\n\n"
        . "SCREENING CENTRE DETAILS\n"
        . "------------------------\n"
        . "Centre: {$facility->facilityName}\n"
        . ($facility->facilityAddress ? "Address: {$facility->facilityAddress}\n" : "")
        . ($facility->facilityLga && $facility->facilityState ? "Location: {$facility->facilityLga}, {$facility->facilityState}\n" : "")
        . ($clinicHours ? "Clinic Hours: {$clinicHours}\n" : "")
        . "\n"
        . "YOUR NAVIGATOR (CONTACT PERSON)\n"
        . "--------------------------------\n"
        . ($navigatorName ? "Name: {$navigatorName}\n" : "")
        . ($navigatorPhone ? "Phone: {$navigatorPhone}\n" : "")
        . ($navigatorEmail ? "Email: {$navigatorEmail}\n" : "")
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
        Log::info('Client email send result', ['to' => $client->email, 'result' => $sent]);
    }

    if (!empty($client->phoneNumber)) {
        $sent = $this->whatsapp->send($client->phoneNumber, $clientWhatsApp);
        Log::info('Client WhatsApp send result', ['to' => $client->phoneNumber, 'result' => $sent]);

        $smsSent = $this->sendSms($client->phoneNumber, $clientSms);
        Log::info('Client SMS send result', ['to' => $client->phoneNumber, 'result' => $smsSent]);
    }

    // ── Navigator WhatsApp message ────────────────────────────────────
    $navigatorWhatsApp =
        "Hello {$navigatorName},\n\n"
        . "🔔 *A new client has been linked to your facility.*\n\n"
        . "👤 *Client Details*\n"
        . "*Name:* {$client->fullName}\n"
        . "*Phone:* {$client->phoneNumber}\n"
        . ($client->email ? "*Email:* {$client->email}\n" : "")
        . "\n"
        . "Please reach out to the client to schedule their screening appointment.\n\n"
        . "_National Cancer Screening Registry (NCSR) — NICRAT_";

    // ── Navigator email message ───────────────────────────────────────
    $navigatorEmail_body =
        "Dear {$navigatorName},\n\n"
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
    if (!empty($navigatorEmail)) {
        $sent = $this->brevo->sendTransactional(
            to:      $navigatorEmail,
            name:    $navigatorName ?? 'Navigator',
            subject: "New Client Linked — {$client->fullName}",
            message: $navigatorEmail_body,
        );
        Log::info('Navigator email send result', ['to' => $navigatorEmail, 'result' => $sent]);
    }

    if (!empty($navigatorPhone)) {
        $sent = $this->whatsapp->send($navigatorPhone, $navigatorWhatsApp);
        Log::info('Navigator WhatsApp send result', ['to' => $navigatorPhone, 'result' => $sent]);
    }
}
}