<?php
// app/Services/OtpService.php

namespace App\Services;

use App\Models\OtpVerification;
use Illuminate\Support\Facades\Log;

class OtpService
{
    public function __construct(
        protected WhatsAppService $whatsapp,
        protected BrevoService $brevo,
        protected BulkSmsService $bulkSms,
    ) {}

    /**
     * Generate and send OTP.
     * Channel order: WhatsApp (Twilio) primary, SMS (BulkSMS Nigeria) fallback, email always if provided.
     */
    public function sendOtp(
        string  $phoneNumber,
        string  $registrationId,
        ?string $email = null,
        ?string $name  = null,
    ): bool {
        // Invalidate existing unverified OTPs
        OtpVerification::where('phoneNumber', $phoneNumber)
            ->where('verified', false)
            ->delete();

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpVerification::create([
            'phoneNumber'    => $phoneNumber,
            'otp'            => $otp,
            'registrationId' => $registrationId,
            'verified'       => false,
            'expiresAt'      => now()->addMinutes(10),
        ]);

        $greeting = $name ? " {$name}" : "";

        // ── WhatsApp first (Twilio) ──────────────────────────────
        $whatsappMessage =
            "Hello{$greeting}, your NCSR verification code is:\n\n"
            . "*{$otp}*\n\n"
            . "This code expires in 10 minutes. "
            . "Do not share it with anyone.";

        $whatsappSent = $this->whatsapp->send($phoneNumber, $whatsappMessage);

        if (!$whatsappSent) {
            Log::error('OTP WhatsApp send failed', [
                'phone' => $phoneNumber,
            ]);
        }

        // ── SMS fallback (BulkSMS Nigeria) — only if WhatsApp failed ──
        $smsSent = false;
        if (!$whatsappSent) {
            $smsMessage =
                "NCSR: Hello{$greeting}, your verification code is {$otp}. "
                . "Expires in 10 minutes. Do not share.";

            $smsSent = $this->bulkSms->send($phoneNumber, $smsMessage);

            if (!$smsSent) {
                Log::error('OTP SMS (BulkSMS Nigeria) send failed', [
                    'phone' => $phoneNumber,
                ]);
            }
        }

        // ── Email (always, if provided) ──────────────────────────
        $emailSent = false;
        if ($email) {
            $emailMessage =
                "Dear{$greeting},\n\n"
                . "Your NCSR phone verification code is:\n\n"
                . "{$otp}\n\n"
                . "Enter this code on the verification page to complete your registration.\n\n"
                . "This code expires in 10 minutes. Do not share it with anyone.\n\n"
                . "If you did not register for cancer screening, please ignore this message.";

            $emailSent = $this->brevo->sendTransactional(
                to:      $email,
                name:    $name ?? 'Registrant',
                subject: "Your NCSR Verification Code — {$otp}",
                message: $emailMessage,
            );

            if (!$emailSent) {
                Log::error('OTP email send failed', ['email' => $email]);
            }
        }

        $anySent = $whatsappSent || $smsSent || $emailSent;

        Log::info('OTP send result', [
            'phone'        => $phoneNumber,
            'email'        => $email ?? 'none',
            'whatsappSent' => $whatsappSent,
            'smsSent'      => $smsSent,
            'emailSent'    => $emailSent,
        ]);

        return $anySent;
    }

    public function verifyOtp(string $phoneNumber, string $otp): array
    {
        $record = OtpVerification::where('phoneNumber', $phoneNumber)
            ->where('verified', false)
            ->latest()
            ->first();

        if (!$record) {
            return [
                'success' => false,
                'message' => 'No OTP found for this number. Please request a new one.',
            ];
        }

        if ($record->isExpired()) {
            return [
                'success' => false,
                'message' => 'OTP has expired. Please request a new one.',
            ];
        }

        if ($record->otp !== $otp) {
            return [
                'success' => false,
                'message' => 'Incorrect OTP. Please try again.',
            ];
        }

        $record->update(['verified' => true]);

        return [
            'success'        => true,
            'registrationId' => $record->registrationId,
        ];
    }
}