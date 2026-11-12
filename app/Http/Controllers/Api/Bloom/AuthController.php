<?php

namespace App\Http\Controllers\Api\Bloom;

use App\Http\Controllers\Controller;
use App\Models\BloomAccount;
use App\Services\Bloom\BloomOtpService;
use App\Support\Bloom\NigerianPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

/**
 * Bloom app sign-up, phone verification, login and password reset.
 * Login is phone number + password. The email is proven with a code sent to it.
 */
class AuthController extends Controller
{
    use Concerns;

    // Used to burn the same time on unknown phones as on real ones.
    private const DUMMY_HASH = '$2y$12$5c2AMdeNUV7fA5sEd035R.D2R52y.UJeDxUX9gMk4jcSpK95eX.9S';

    public function __construct(private BloomOtpService $otp)
    {
    }

    /** POST /bloom/auth/register */
    public function register(Request $request): JsonResponse
    {
        $this->normalizePhoneInput($request);
        $this->normalizeEmailInput($request);

        $data = $request->validate([
            'name'     => ['required', 'string', 'min:2', 'max:80'],
            'phone'    => $this->phoneRules(),
            'email'    => ['required', 'string', 'email:rfc', 'max:150'],
            'password' => $this->passwordRules(),
            'age'      => ['nullable', 'integer', 'between:10,110'],
            'need'     => ['nullable', 'string', Rule::in(self::NEEDS)],
        ], $this->phoneMessages());

        $existing = BloomAccount::where('phone', $data['phone'])->first();

        if ($existing && $existing->isVerified()) {
            return $this->fail(
                'PHONE_TAKEN',
                'This number already has a Bloom account. Try signing in.',
                422,
                ['errors' => ['phone' => ['This number already has a Bloom account. Try signing in.']]]
            );
        }

        $emailOwner = BloomAccount::where('email', $data['email'])->where('phone', '!=', $data['phone'])->first();
        if ($emailOwner) {
            if ($emailOwner->isVerified()) {
                return $this->fail(
                    'EMAIL_TAKEN',
                    'This email already has a Bloom account. Try signing in.',
                    422,
                    ['errors' => ['email' => ['This email already has a Bloom account. Try signing in.']]]
                );
            }
            $emailOwner->delete();   // abandoned, never-verified sign-up: free the email
        }

        // An unfinished sign-up for the same number is simply updated, so nobody
        // can lock a number by starting (and abandoning) a registration.
        $account = $existing ?? new BloomAccount(['phone' => $data['phone'], 'status' => 'active']);
        $account->fill([
            'name'     => trim($data['name']),
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'age'      => $data['age'] ?? null,
            'need'     => $data['need'] ?? null,
        ])->save();

        $result = $this->otp->issue($account->phone, BloomOtpService::PURPOSE_VERIFY, $account->email, $account->name);
        if (!$result['ok']) {
            return $this->otpFailure($result);
        }

        return response()->json(array_filter([
            'status'         => true,
            'code'           => 'OTP_SENT',
            'message'        => 'We emailed a 6-digit code to ' . $this->maskedEmail($account->email) . '.',
            'phone'          => NigerianPhone::local($account->phone),
            'email'          => $this->maskedEmail($account->email),
            'otp_expires_in' => (int) config('bloom.otp.ttl_minutes') * 60,
            'debug_code'     => $result['debug_code'] ?? null,
        ], fn ($v) => $v !== null), 201);
    }

    /** POST /bloom/auth/verify */
    public function verify(Request $request): JsonResponse
    {
        $this->normalizePhoneInput($request);

        $data = $request->validate([
            'phone' => $this->phoneRules(),
            'code'  => ['required', 'digits:' . (int) config('bloom.otp.length')],
        ], $this->phoneMessages());

        $account = BloomAccount::where('phone', $data['phone'])->first();

        // Same answer whether the number exists or not.
        $outcome = $account
            ? $this->otp->check($account->phone, BloomOtpService::PURPOSE_VERIFY, $data['code'])
            : BloomOtpService::INVALID;

        if ($outcome !== BloomOtpService::OK) {
            return $this->codeFailure($outcome);
        }

        if (!$account->isActive()) {
            return $this->fail('ACCOUNT_INACTIVE', 'Your account is inactive. Please contact support.', 403);
        }

        $account->forceFill(['phone_verified_at' => $account->phone_verified_at ?? now()])->save();

        return $this->tokenResponse($account, 'Account confirmed.');
    }

    /** POST /bloom/auth/resend  (sign-up code) */
    public function resend(Request $request): JsonResponse
    {
        $this->normalizePhoneInput($request);
        $data = $request->validate(['phone' => $this->phoneRules()], $this->phoneMessages());

        $account = BloomAccount::where('phone', $data['phone'])->first();

        // Only unverified accounts can ask for a sign-up code; stay quiet otherwise.
        if ($account && !$account->isVerified() && $account->isActive()) {
            $result = $this->otp->issue($account->phone, BloomOtpService::PURPOSE_VERIFY, $account->email, $account->name);
            if (!$result['ok']) {
                return $this->otpFailure($result);
            }

            return response()->json(array_filter([
                'status'         => true,
                'code'           => 'OTP_SENT',
                'message'        => 'We emailed you a new code.',
                'email'          => $this->maskedEmail($account->email),
                'otp_expires_in' => (int) config('bloom.otp.ttl_minutes') * 60,
                'debug_code'     => $result['debug_code'] ?? null,
            ], fn ($v) => $v !== null));
        }

        return response()->json(['status' => true, 'code' => 'OTP_SENT', 'message' => 'If that number is waiting for a code, we emailed a new one.']);
    }

    /** POST /bloom/auth/login */
    public function login(Request $request): JsonResponse
    {
        $this->normalizePhoneInput($request);

        $data = $request->validate([
            'phone'    => $this->phoneRules(),
            'password' => ['required', 'string', 'max:200'],
        ], $this->phoneMessages());

        $account = BloomAccount::where('phone', $data['phone'])->first();

        $passwordOk = Hash::check($data['password'], $account?->password ?? self::DUMMY_HASH);

        if (!$account || !$passwordOk) {
            return $this->fail('INVALID_CREDENTIALS', 'Wrong phone number or password.', 401);
        }

        if (!$account->isActive()) {
            return $this->fail('ACCOUNT_INACTIVE', 'Your account is inactive. Please contact support.', 403);
        }

        if (!$account->isVerified()) {
            // Right password, but the number was never confirmed: send a fresh code
            // and let the app move straight to the code screen.
            $result = $this->otp->issue($account->phone, BloomOtpService::PURPOSE_VERIFY, $account->email, $account->name);

            return $this->fail(
                'PHONE_NOT_VERIFIED',
                'Please confirm your account. We emailed you a code.',
                403,
                array_filter([
                    'phone'      => NigerianPhone::local($account->phone),
                    'email'      => $this->maskedEmail($account->email),
                    'debug_code' => $result['debug_code'] ?? null,
                ])
            );
        }

        return $this->tokenResponse($account, 'Signed in.');
    }

    /** POST /bloom/auth/forgot */
    public function forgot(Request $request): JsonResponse
    {
        $this->normalizePhoneInput($request);
        $data = $request->validate(['phone' => $this->phoneRules()], $this->phoneMessages());

        $account = BloomAccount::where('phone', $data['phone'])->first();
        $debug   = null;

        if ($account && $account->isActive() && $account->isVerified()) {
            $result = $this->otp->issue($account->phone, BloomOtpService::PURPOSE_RESET, $account->email, $account->name);
            if (!$result['ok'] && ($result['reason'] ?? '') === 'too_soon') {
                return $this->otpFailure($result);
            }
            $debug = $result['debug_code'] ?? null;
        }

        // Identical answer for known and unknown numbers.
        return response()->json(array_filter([
            'status'         => true,
            'code'           => 'OTP_SENT',
            'message'        => 'If that number has a Bloom account, we emailed its owner a reset code.',
            'otp_expires_in' => (int) config('bloom.otp.ttl_minutes') * 60,
            'debug_code'     => $debug,
        ], fn ($v) => $v !== null));
    }

    /** POST /bloom/auth/reset */
    public function reset(Request $request): JsonResponse
    {
        $this->normalizePhoneInput($request);

        $data = $request->validate([
            'phone'    => $this->phoneRules(),
            'code'     => ['required', 'digits:' . (int) config('bloom.otp.length')],
            'password' => $this->passwordRules(),
        ], $this->phoneMessages());

        $account = BloomAccount::where('phone', $data['phone'])->first();

        $outcome = ($account && $account->isActive() && $account->isVerified())
            ? $this->otp->check($account->phone, BloomOtpService::PURPOSE_RESET, $data['code'])
            : BloomOtpService::INVALID;

        if ($outcome !== BloomOtpService::OK) {
            return $this->codeFailure($outcome);
        }

        $account->forceFill(['password' => Hash::make($data['password'])])->save();

        return $this->tokenResponse($account, 'Password changed.');
    }

    /** GET /bloom/auth/me */
    public function me(): JsonResponse
    {
        return response()->json(['status' => true, 'user' => $this->accountPayload($this->account())]);
    }

    /** POST /bloom/auth/logout */
    public function logout(): JsonResponse
    {
        Auth::guard('bloom')->logout();   // puts the token on the JWT blacklist

        return response()->json(['status' => true, 'message' => 'Signed out.']);
    }

    private function codeFailure(string $outcome): JsonResponse
    {
        return match ($outcome) {
            BloomOtpService::EXPIRED => $this->fail('CODE_EXPIRED', 'That code has expired. Ask for a new one.', 422),
            BloomOtpService::LOCKED  => $this->fail('CODE_LOCKED', 'Too many wrong tries. Ask for a new code.', 429),
            default                  => $this->fail('INVALID_CODE', "That code isn't right. Check it and try again.", 422),
        };
    }
}
