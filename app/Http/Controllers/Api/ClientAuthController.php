<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientLoginToken;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClientAuthController extends Controller
{
    public function __construct(protected OtpService $otpService) {}

    /**
     * Step 1 — client enters their phone number. We look up the Client
     * by phone (they must already have a record — the portal doesn't
     * self-register new clients) and send a login OTP.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phoneNumber' => ['required', 'string'],
        ]);

        $client = Client::where('phoneNumber', $request->phoneNumber)->first();

        if (!$client) {
            // Don't reveal whether the number exists — generic message either way.
            return response()->json([
                'message' => 'If this number is registered, a login code has been sent.',
            ]);
        }

        $sent = $this->otpService->sendLoginOtp(
            $client->phoneNumber,
            $client->email,
            $client->fullName,
        );

        if (!$sent) {
            return response()->json([
                'message' => 'Could not send a login code right now. Please try again shortly.',
            ], 500);
        }

        $clean = preg_replace('/\D/', '', $client->phoneNumber);
        $maskedPhone = substr($clean, 0, 3) . '****' . substr($clean, -4);

        return response()->json([
            'message' => 'If this number is registered, a login code has been sent.',
            'maskedPhone' => $maskedPhone,
        ]);
    }

    /**
     * Step 2 — verify the OTP and issue a portal session token.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phoneNumber' => ['required', 'string'],
            'otp' => ['required', 'string'],
        ]);

        $result = $this->otpService->verifyOtp($request->phoneNumber, $request->otp);

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 422);
        }

        $client = Client::where('phoneNumber', $request->phoneNumber)->first();

        if (!$client) {
            return response()->json(['message' => 'Client record not found.'], 404);
        }

        $token = ClientLoginToken::create([
            'token' => Str::random(64),
            'clientId' => $client->clientId,
            'expiresAt' => now()->addDays(7),
        ]);

        return response()->json([
            'message' => 'Login successful.',
            'token' => $token->token,
            'client' => [
                'clientId' => $client->clientId,
                'fullName' => $client->fullName,
            ],
        ]);
    }

    /**
     * Log out — invalidate the current portal session token.
     */
    public function logout(Request $request): JsonResponse
    {
        $tokenString = $request->bearerToken();

        if ($tokenString) {
            ClientLoginToken::where('token', $tokenString)->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }
}
