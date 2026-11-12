<?php

namespace App\Services\Bloom;

use App\Models\BloomOtp;
use Illuminate\Support\Facades\Log;

/**
 * One-time codes for Bloom sign-up and password reset.
 *
 * Codes are sent by email (BloomMailService / Brevo). Only an HMAC of the
 * code is stored. A code works once, expires, and locks after too many wrong tries.
 */
class BloomOtpService
{
    public const PURPOSE_VERIFY = 'verify';
    public const PURPOSE_RESET  = 'reset';

    public const OK       = 'ok';
    public const INVALID  = 'invalid';
    public const EXPIRED  = 'expired';
    public const LOCKED   = 'locked';

    public function __construct(private BloomMailService $mail)
    {
    }

    /**
     * $phone identifies the account; the code is emailed to $email.
     *
     * @return array{ok: bool, reason?: string, retry_after?: int, debug_code?: string}
     */
    public function issue(string $phone, string $purpose, ?string $email, ?string $name = null): array
    {
        $resendAfter = (int) config('bloom.otp.resend_after');

        if (!$email) {
            return ['ok' => false, 'reason' => 'no_email'];
        }

        $recent = BloomOtp::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('created_at', '>', now()->subSeconds($resendAfter))
            ->latest('id')
            ->first();

        if ($recent) {
            $wait = max(1, $resendAfter - (int) $recent->created_at->diffInSeconds(now()));
            return ['ok' => false, 'reason' => 'too_soon', 'retry_after' => $wait];
        }

        // Only the newest code is ever valid.
        BloomOtp::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $length = (int) config('bloom.otp.length');
        $code   = str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
        $ttl    = (int) config('bloom.otp.ttl_minutes');

        $otp = BloomOtp::create([
            'phone'      => $phone,
            'purpose'    => $purpose,
            'code_hash'  => $this->hash($phone, $purpose, $code),
            'attempts'   => 0,
            'expires_at' => now()->addMinutes($ttl),
        ]);

        $debug = (bool) config('bloom.otp.debug');
        $sent  = $this->mail->sendCode($email, $name, $code, $purpose, $ttl);

        if ($debug) {
            Log::info('Bloom OTP (debug mode)', ['phone' => $phone, 'purpose' => $purpose, 'code' => $code]);
            return ['ok' => true, 'debug_code' => $code];
        }

        if (!$sent) {
            $otp->update(['consumed_at' => now()]);
            return ['ok' => false, 'reason' => 'send_failed'];
        }

        return ['ok' => true];
    }

    /** Returns one of OK, INVALID, EXPIRED, LOCKED. A correct code is consumed. */
    public function check(string $phone, string $purpose, string $code): string
    {
        $otp = BloomOtp::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (!$otp) {
            return self::INVALID;
        }
        if ($otp->expires_at->isPast()) {
            return self::EXPIRED;
        }
        if ($otp->attempts >= (int) config('bloom.otp.max_attempts')) {
            return self::LOCKED;
        }

        $otp->increment('attempts');

        if (!hash_equals($otp->code_hash, $this->hash($phone, $purpose, $code))) {
            return self::INVALID;
        }

        $otp->update(['consumed_at' => now()]);

        return self::OK;
    }

    private function hash(string $phone, string $purpose, string $code): string
    {
        return hash_hmac('sha256', "{$phone}|{$purpose}|{$code}", (string) config('app.key'));
    }
}
