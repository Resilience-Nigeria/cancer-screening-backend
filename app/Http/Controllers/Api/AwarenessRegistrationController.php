<?php

namespace App\Http\Controllers\Api;

use App\Events\ClientLinkedToScreeningCenter;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAwarenessRegistrationRequest;
use App\Models\AwarenessRegistration;
use App\Services\FacilityService;
use Illuminate\Http\JsonResponse;

class AwarenessRegistrationController extends Controller
{
    public function __construct(protected FacilityService $facilityService) {}

    public function store(StoreAwarenessRegistrationRequest $request): JsonResponse
    {
        $registration = AwarenessRegistration::create($request->validated());

        // Auto-link to nearest sub-hub
        $facility = $this->facilityService->findNearestScreeningFacility(
            $request->stateOfResidence,
            $request->lgaOfResidence,
        );

        if ($facility) {
            $registration->update(['status' => 'linked']);

            // Fire notification event — listener handles email + WhatsApp
            ClientLinkedToScreeningCenter::dispatch(
                (object) [ // lightweight stand-in until converted to full client
                    'fullName'     => $registration->fullName,
                    'email'        => $registration->email,
                    'phoneNumber'  => $registration->phoneNumber,
                ],
                $facility,
            );
        }

        return response()->json([
            'message'      => 'Registration successful.',
            'registration' => $registration,
            'facility'     => $facility?->only([
                'facilityName',
                'facilityAddress',
                'navigatorName',
                'navigatorPhone',
            ]),
        ], 201);
    }
}