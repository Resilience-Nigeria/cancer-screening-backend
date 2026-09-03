<?php

namespace App\Http\Controllers\Api;

use App\Events\ClientLinkedToScreeningCenter;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAwarenessRegistrationRequest;
use App\Models\AwarenessRegistration;
use App\Models\Navigator;
use App\Services\FacilityService;
use App\Services\OtpService;
use App\Services\BrevoService;
use App\Services\WhatsAppService;
use App\Services\SmsService;
use App\Services\NavigatorService;

use Illuminate\Http\JsonResponse;

class AwarenessRegistrationController extends Controller
{
    public function __construct(
    protected FacilityService $facilityService,
    protected OtpService $otpService,
    protected BrevoService $brevo,
    protected WhatsAppService $whatsapp,
    protected SmsService      $sms,       // 👈 add
    protected NavigatorService $navigatorService, 

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

    // If this registration is being submitted by a logged-in navigator
    // (e.g. the "new client" step of Stage 2 — Clinical Screening, which
    // hits this same endpoint), skip location-based matching entirely and
    // assign the client straight to that navigator and their facility,
    // regardless of the state/LGA/area they entered. The public,
    // unauthenticated Bloom self-registration form has no logged-in
    // navigator, so it keeps the existing nearest-facility-by-location
    // behaviour unchanged.
    $loggedInNavigator = $this->resolveLoggedInNavigator();

    if ($loggedInNavigator) {
        $facility  = $loggedInNavigator->facility ?? \App\Models\Facility::find($loggedInNavigator->facilityId);
        $navigator = $loggedInNavigator;
    } else {
        $facility = $this->facilityService->findNearestScreeningFacility(
            state: $request->stateOfResidence,
            lga:   $request->lgaOfResidence,
            area:  $request->areaOfResidence,  // 👈 new
        );

        $navigator = $facility ? $this->navigatorService->assignNavigator($facility) : null;
    }

    // Persist the resolved coordinates on the registration itself —
    // area-level match first (most precise), falling back to the LGA's
    // center — so the client's approximate location is actually usable
    // later (mapping, distance reporting), not just used transiently
    // for facility matching above and then discarded.
    $coordinates = $this->resolveCoordinates(
        $request->stateOfResidence,
        $request->lgaOfResidence,
        $request->areaOfResidence,
    );

    $registration->update([
        'status'             => $facility ? 'linked' : 'pending',
        'linkedFacilityId'   => $facility?->facilityId,
        'navigatorId'        => $navigator?->id,
        ...$coordinates,
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
        'facility'       => $facility ? [
            'facilityName' => $facility->facilityName,
            'facilityAddress' => $facility->facilityAddress,
            'navigatorName' => trim(
            $navigator?->user?->firstName . ' ' .
            $navigator?->user?->lastName . ' ' .
            $navigator?->user?->otherNames
            ),

            'navigatorPhone' => $facility->navigatorPhone,
            'clinicHoursDisplay' => $facility->formatClinicHours(),
        ] : null,
    ], 201);
}

/**
 * Resolves the Navigator profile for the currently authenticated user, if
 * any. Returns null for anonymous/public submissions (the Bloom
 * self-registration form) or for an authenticated user who isn't an
 * active navigator, so the caller can fall back to the normal
 * nearest-facility matching in either case.
 */
private function resolveLoggedInNavigator(): ?Navigator
{
    $user = auth('api')->user();

    if (!$user) {
        return null;
    }

    return Navigator::where('userId', $user->id)
        ->where('isActive', true)
        ->first();
}

private function maskPhone(string $phone): string
{
    $clean = preg_replace('/\D/', '', $phone);
    return substr($clean, 0, 3) . '****' . substr($clean, -4);
}

/**
 * Resolves the best-available coordinates for a registration: an exact
 * area/district match first (most precise), falling back to the LGA's
 * average/center coordinates if no area-level match exists. Returns an
 * empty array (no keys) if neither is found, so it merges harmlessly
 * into the update() call above.
 */
private function resolveCoordinates(string $state, string $lga, ?string $area): array
{
    if ($area) {
        $match = \Illuminate\Support\Facades\DB::table('areaCoordinates')
            ->whereRaw('LOWER(state) = ?', [strtolower($state)])
            ->whereRaw('LOWER(lga) = ?', [strtolower($lga)])
            ->whereRaw('LOWER(area) = ?', [strtolower($area)])
            ->first();

        if ($match) {
            return [
                'latitude' => $match->latitude,
                'longitude' => $match->longitude,
                'coordinateSource' => 'area',
            ];
        }
    }

    $lgaCenter = \Illuminate\Support\Facades\DB::table('areaCoordinates')
        ->whereRaw('LOWER(state) = ?', [strtolower($state)])
        ->whereRaw('LOWER(lga) = ?', [strtolower($lga)])
        ->selectRaw('AVG(latitude) as latitude, AVG(longitude) as longitude')
        ->first();

    if ($lgaCenter && $lgaCenter->latitude !== null) {
        return [
            'latitude' => $lgaCenter->latitude,
            'longitude' => $lgaCenter->longitude,
            'coordinateSource' => 'lga',
        ];
    }

    return [];
}

/**
 * Used by Stage 2 (Clinical Screening) to find a prior Bloom
 * self-assessment for a client who hasn't been registered as a
 * full Client yet — Bloom only creates an AwarenessRegistration,
 * never a Client, so Stage 2's normal client lookup can't see it.
 */
public function lookupByPhone(\Illuminate\Http\Request $request): JsonResponse
{
    $phone = trim((string) $request->query('phone', ''));

    if (!$phone) {
        return response()->json(['registration' => null]);
    }

    $registration = AwarenessRegistration::where('phoneNumber', $phone)
        ->latest('registrationId')
        ->with(['selfAssessments' => fn ($q) => $q->latest('assessmentId')->limit(1)])
        ->first();

    if (!$registration) {
        return response()->json(['registration' => null]);
    }

    return response()->json([
        'registration' => $registration,
        'selfAssessment' => $registration->selfAssessments->first(),
    ]);
}

}