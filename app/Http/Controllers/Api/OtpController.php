<?php
// app/Http/Controllers/Api/OtpController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AwarenessRegistration;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OtpController extends Controller
{
    public function __construct(protected OtpService $otpService) {}

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phoneNumber'    => ['required', 'string'],
            'registrationId' => ['required', 'string'],
        ]);

        $sent = $this->otpService->sendOtp(
            $data['phoneNumber'],
            $data['registrationId'],
        );

        if (!$sent) {
            return response()->json([
                'message' => 'Failed to send OTP. Please check your number and try again.',
            ], 500);
        }

        return response()->json([
            'message' => 'OTP sent successfully.',
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phoneNumber' => ['required', 'string'],
            'otp'         => ['required', 'string', 'size:6'],
        ]);

        $result = $this->otpService->verifyOtp($data['phoneNumber'], $data['otp']);

        if (!$result['success']) {
            return response()->json(['message' => $result['message']], 422);
        }

        // In OtpController::verify(), after $result['success']:
$registration = AwarenessRegistration::find($result['registrationId']);

if ($registration && $registration->status === 'linked') {
    $facility = \App\Models\Facility::find($registration->linkedFacilityId);

    if ($facility) {
        \App\Events\ClientLinkedToScreeningCenter::dispatch(
            (object) [
                'fullName'    => $registration->fullName,
                'email'       => $registration->email,
                'phoneNumber' => $registration->phoneNumber,
            ],
            $facility,
        );
    }
}

        // Load the registration so we can return facility details
        // $registration = AwarenessRegistration::with('facility')
        //     ->find($result['registrationId']);

        return response()->json([
            'message'      => 'Phone number verified successfully.',
            'registration' => $registration,
            'facility'     => $registration?->facility ? [
                'facilityName' => $registration->facility->facilityName,
                'facilityAddress' => $registration->facility->facilityAddress,
                'navigatorName' => $registration->facility->navigatorName,
                'navigatorPhone' => $registration->facility->navigatorPhone,
                'clinicHoursDisplay' => $registration->facility->formatClinicHours(),
            ] : null,
        ]);
    }

   public function resend(Request $request): JsonResponse
{
    $data = $request->validate([
        'phoneNumber'    => ['required', 'string'],
        'registrationId' => ['required', 'string'],
        'email'          => ['nullable', 'email'],   // 👈 add
        'name'           => ['nullable', 'string'],  // 👈 add
    ]);

    $sent = $this->otpService->sendOtp(
        phoneNumber: $data['phoneNumber'],
        registrationId: $data['registrationId'],
        email: $data['email'] ?? null,               // 👈 add
        name: $data['name'] ?? null,                 // 👈 add
    );

    return response()->json([
        'message' => $sent
            ? 'A new OTP has been sent to your WhatsApp'
                . ($data['email'] ? ' and email' : '') . '.'
            : 'Failed to resend OTP. Please try again.',
    ], $sent ? 200 : 500);
}
}