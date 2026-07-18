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
        protected SmsService $sms,        // 👈 inject SMS service
    ) {}

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

        $greeting       = $name ? " {$name}" : "";
        $whatsappMessage =
            "Hello{$greeting}, your NCSR verification code is:\n\n"
            . "*{$otp}*\n\n"
            . "This code expires in 10 minutes. "
            . "Do not share it with anyone.";

        $smsMessage =
            "NCSR: Hello{$greeting}, your verification code is {$otp}. "
            . "Expires in 10 minutes. Do not share.";
        $smsSent = $this->sms->send($phoneNumber, $smsMessage);
        // ── WhatsApp first, SMS fallback ──────────────────────────────
        // $whatsappStatus = $this->whatsapp->sendWithStatus($phoneNumber, $whatsappMessage);

        // $smsSent       = false;
        // $whatsappSent  = $whatsappStatus === 'sent';

        // if (!$whatsappSent) {
        //     Log::info('OTP: WhatsApp failed, falling back to SMS', [
        //         'phone'           => $phoneNumber,
        //         'whatsapp_status' => $whatsappStatus,
        //     ]);
        //     $smsSent = $this->sms->send($phoneNumber, $smsMessage);
        // }

        // ── Email ─────────────────────────────────────────────────────
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
        }

        Log::info('OTP send result', [
            'phone'        => $phoneNumber,
            'email'        => $email ?? 'none',
            // 'whatsappSent' => $whatsappSent,
            'smsSent'      => $smsSent,
            'emailSent'    => $emailSent,
        ]);

        return  $smsSent || $emailSent;
        // return $whatsappSent || $smsSent || $emailSent;

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