<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AwarenessRegistration;
use App\Services\FacilityService;
use App\Services\OtpService;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OtpController extends Controller
{
    public function __construct(
        protected OtpService $otpService,
        protected FacilityService $facilityService,  
        protected SmsService      $sms,     

    ) {}

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phoneNumber'    => ['required', 'string'],
            'registrationId' => ['required', 'string'],
        ]);

        $sent = $this->otpService->sendOtp(
            phoneNumber:    $data['phoneNumber'],
            registrationId: $data['registrationId'],
        );

        if (!$sent) {
            return response()->json([
                'message' => 'Failed to send OTP. Please check your number and try again.',
            ], 500);
        }

        return response()->json(['message' => 'OTP sent successfully.']);
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

    // Load registration with the facility that was matched at registration time
    $registration = AwarenessRegistration::with('facility')->find($result['registrationId']);

    // Use the stored facility — same one that was matched during store()
    $facility = $registration?->facility;

    // If for some reason it's null, fall back to re-running the lookup
    if (!$facility && $registration) {
        $facility = $this->facilityService->findNearestScreeningFacility(
            state: $registration->stateOfResidence,
            lga:   $registration->lgaOfResidence,
            area:  $registration->areaOfResidence ?? null,
        );
    }

    // Fire linkage notifications now that phone is verified
    if ($registration && $facility) {
        \App\Events\ClientLinkedToScreeningCenter::dispatch(
            (object) [
                'fullName'    => $registration->fullName,
                'email'       => $registration->email,
                'phoneNumber' => $registration->phoneNumber,
            ],
            $facility,
        );
    }

    return response()->json([
        'message'      => 'Phone number verified successfully.',
        'registration' => $registration,
        'facility'     => $facility ? [
            'facilityName'    => $facility->facilityName,
            'facilityAddress' => $facility->facilityAddress,
            'navigatorName'   => $facility->navigatorName,
            'navigatorPhone'  => $facility->navigatorPhone,
        ] : null,
    ]);
}

    public function resend(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phoneNumber'    => ['required', 'string'],
            'registrationId' => ['required', 'string'],
            'email'          => ['nullable', 'email'],
            'name'           => ['nullable', 'string'],
        ]);

        $sent = $this->otpService->sendOtp(
            phoneNumber:    $data['phoneNumber'],
            registrationId: $data['registrationId'],
            email:          $data['email'] ?? null,
            name:           $data['name']  ?? null,
        );

        return response()->json([
            'message' => $sent
                ? 'A new OTP has been sent.'
                : 'Failed to resend OTP. Please try again.',
        ], $sent ? 200 : 500);
    }
}