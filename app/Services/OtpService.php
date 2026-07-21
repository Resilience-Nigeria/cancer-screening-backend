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
     * Generate and send OTP to phone (WhatsApp) and email (if provided).
     * registrationId is nullable — sendLoginOtp() below uses this same
     * method with no registration attached, for the client portal login.
     */
    public function sendOtp(
        string $phoneNumber,
        ?string $registrationId = null,
        ?string $email = null,
        ?string $name = null,
        ?string $purpose = null,
    ): bool {
        // Invalidate any existing unverified OTPs for this number
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

        $action = $purpose === 'login' ? 'log-in' : 'verification';

        $whatsappMessage =
            "Hello{$this->greeting($name)}, your NCSR {$action} code is:\n\n"
            . "*{$otp}*\n\n"
            . "This code expires in 10 minutes. "
            . "Do not share it with anyone.";

        $whatsappSent = $this->whatsapp->send($phoneNumber, $whatsappMessage);

        if (!$whatsappSent) {
            Log::error('OTP WhatsApp send failed', [
                'phone' => $phoneNumber,
            ]);
        }

        // Fallback: WhatsApp is the primary channel, but not every number
        // has WhatsApp — send a plain SMS via BulkSMS Nigeria if it failed,
        // so the OTP still reaches the recipient.
        $smsSent = false;
        if (!$whatsappSent) {
            $smsMessage = "Your NCSR {$action} code is {$otp}. It expires in 10 minutes. Do not share it with anyone.";
            $smsSent = $this->bulkSms->send($phoneNumber, $smsMessage);

            if (!$smsSent) {
                Log::error('OTP SMS (BulkSMS Nigeria) send failed', [
                    'phone' => $phoneNumber,
                ]);
            }
        }

        $emailSent = false;
        if ($email) {
            $emailMessage =
                "Hello{$this->greeting($name)},\n\n"
                . "Your NCSR {$action} code is:\n\n"
                . "{$otp}\n\n"
                . "Enter this code to continue.\n\n"
                . "This code expires in 10 minutes. Do not share it with anyone.\n\n"
                . "If you did not request this, please ignore this message.";

            $emailSent = $this->brevo->sendTransactional(
                to: $email,
                name: $name ?? 'there',
                subject: 'Your NCSR ' . ($purpose === 'login' ? 'Login' : 'Verification') . ' Code — ' . $otp,
                message: $emailMessage,
            );

            if (!$emailSent) {
                Log::error('OTP email send failed', ['email' => $email]);
            }
        }

        // Return true if at least one channel succeeded
        $anySent = $whatsappSent || $smsSent || $emailSent;

        Log::info('OTP send result', [
            'phone'        => $phoneNumber,
            'email'        => $email ?? 'none',
            'purpose'      => $purpose ?? 'registration',
            'whatsappSent' => $whatsappSent,
            'smsSent'      => $smsSent,
            'emailSent'    => $emailSent,
        ]);

        return $anySent;
    }

    /**
     * Client portal login — no registrationId, since the client already
     * has a Client record and isn't registering anything new.
     */
    public function sendLoginOtp(string $phoneNumber, ?string $email = null, ?string $name = null): bool
    {
        return $this->sendOtp($phoneNumber, null, $email, $name, 'login');
    }

    private function greeting(?string $name): string
    {
        return $name ? " {$name}" : "";
    }

    /**
     * Verify submitted OTP.
     */
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