<?php

namespace App\Http\Controllers\Api\Bloom;

use App\Models\BloomAccount;
use App\Support\Bloom\NigerianPhone;
use App\Services\Bloom\BloomMailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

/**
 * Small helpers shared by the Bloom controllers.
 */
trait Concerns
{
    protected const NEEDS = ['breast-health', 'screening', 'appointment', 'community'];

    /** Put the phone in 234XXXXXXXXXX form before validation, whatever way it was typed. */
    protected function normalizePhoneInput(Request $request): void
    {
        $request->merge([
            'phone' => NigerianPhone::normalize((string) $request->input('phone')) ?? $request->input('phone'),
        ]);
    }

    protected function normalizeEmailInput(Request $request): void
    {
        if ($request->has('email')) {
            $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        }
    }

    protected function maskedEmail(?string $email): ?string
    {
        return $email ? BloomMailService::mask($email) : null;
    }

    protected function phoneRules(): array
    {
        return ['required', 'string', 'regex:/^234[789]\d{9}$/'];
    }

    protected function phoneMessages(): array
    {
        return ['phone.regex' => 'Enter a valid Nigerian mobile number, like 0803 123 4567.'];
    }

    protected function passwordRules(): array
    {
        return ['required', 'string', Password::min(8)->letters()->numbers()];
    }

    protected function account(): BloomAccount
    {
        /** @var BloomAccount $account */
        $account = Auth::guard('bloom')->user();

        return $account;
    }

    protected function accountPayload(BloomAccount $account): array
    {
        return [
            'id'            => $account->id,
            'name'          => $account->name,
            'phone'         => NigerianPhone::local($account->phone),
            'email'         => $account->email,
            'age'           => $account->age,
            'need'          => $account->need,
            'phoneVerified' => $account->isVerified(),
            'createdAt'     => optional($account->created_at)->toIso8601String(),
        ];
    }

    /** Issue a login token and the standard signed-in response. */
    protected function tokenResponse(BloomAccount $account, string $message, int $status = 200): JsonResponse
    {
        $ttlMinutes = max(1, (int) config('bloom.token_ttl_days')) * 24 * 60;

        $token = Auth::guard('bloom')->setTTL($ttlMinutes)->login($account);

        $account->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'status'       => true,
            'message'      => $message,
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => $ttlMinutes * 60,
            'user'         => $this->accountPayload($account),
        ], $status);
    }

    protected function fail(string $code, string $message, int $http, array $extra = []): JsonResponse
    {
        return response()->json(array_merge([
            'status'  => false,
            'code'    => $code,
            'message' => $message,
        ], $extra), $http);
    }

    /** Maps a failed BloomOtpService::issue() result to a response. */
    protected function otpFailure(array $result): JsonResponse
    {
        if (($result['reason'] ?? '') === 'too_soon') {
            return $this->fail(
                'OTP_TOO_SOON',
                'A code was sent a moment ago. Please wait before asking for another.',
                429,
                ['retry_after' => $result['retry_after'] ?? 60]
            );
        }

        if (($result['reason'] ?? '') === 'no_email') {
            return $this->fail('NO_EMAIL', 'This account has no email address yet. Please create the account again.', 422);
        }

        return $this->fail(
            'OTP_SEND_FAILED',
            "We couldn't send the email right now. Please try again in a few minutes.",
            503
        );
    }
}
