<?php

namespace App\Http\Controllers\Api;

use App\Events\ClientLinkedToScreeningCenter;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAwarenessRegistrationRequest;
use App\Models\AwarenessRegistration;
use App\Services\FacilityService;
use App\Services\OtpService;
use App\Services\BrevoService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;

class AwarenessRegistrationController extends Controller
{
    public function __construct(
    protected FacilityService $facilityService,
    protected OtpService $otpService,
    protected BrevoService $brevo,
    protected WhatsAppService $whatsapp,
) {}
    // public function __construct(protected FacilityService $facilityService) {}

//     public function store(StoreAwarenessRegistrationRequest $request): JsonResponse
//     {
//         $registration = AwarenessRegistration::create($request->validated());

//         // Auto-link to nearest sub-hub
//         $facility = $this->facilityService->findNearestScreeningFacility(
//             $request->stateOfResidence,
//             $request->lgaOfResidence,
//         );

//         if ($facility) {
//             $registration->update(['status' => 'linked']);

//             // Fire notification event — listener handles email + WhatsApp
//             ClientLinkedToScreeningCenter::dispatch(
//                 (object) [ // lightweight stand-in until converted to full client
//                     'fullName'     => $registration->fullName,
//                     'email'        => $registration->email,
//                     'phoneNumber'  => $registration->phoneNumber,
//                 ],
//                 $facility,
//             );
//         }

//         return response()->json([
//             'message'      => 'Registration successful.',
//             'registration' => $registration,
//             'facility'     => $facility?->only([
//                 'facilityName',
//                 'facilityAddress',
//                 'navigatorName',
//                 'navigatorPhone',
//             ]),
//         ], 201);
//     }
// }



public function store(StoreAwarenessRegistrationRequest $request): JsonResponse
{
    $registration = AwarenessRegistration::create($request->validated());

    $facility = $this->facilityService->findNearestScreeningFacility(
        $request->stateOfResidence,
        $request->lgaOfResidence,
    );

    $registration->update([
        'status'             => $facility ? 'linked' : 'pending',
        'linkedFacilityId'   => $facility?->facilityId,
    ]);

    // Send OTP — full notifications fire after verification
    $this->otpService->sendOtp(
    phoneNumber: $request->phoneNumber,
    registrationId: (string) $registration->registrationId,
    email: $request->email,        // 👈 add
    name: $request->fullName,      // 👈 add
);

    return response()->json([
        'message'        => 'Registration saved. Please verify your phone number.',
        'registrationId' => $registration->registrationId,
        'phoneNumber'    => $request->phoneNumber,
        // Mask the number for display: 080****4875
        'maskedPhone'    => $this->maskPhone($request->phoneNumber),
        'facility'       => $facility?->only([   // 👈 confirm this is still here
        'facilityName',
        'facilityAddress',
        'navigatorName',
        'navigatorPhone',
    ]),
    ], 201);
}

private function maskPhone(string $phone): string
{
    $clean = preg_replace('/\D/', '', $phone);
    return substr($clean, 0, 3) . '****' . substr($clean, -4);
}

}