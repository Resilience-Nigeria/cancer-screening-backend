<?php
// app/Http/Controllers/Api/ClientReferralController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientReferral;
use App\Models\Facility;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientReferralController extends Controller
{
    public function __construct(protected ReferralService $referralService) {}

    public function store(Request $request, Client $client): JsonResponse
    {
        $data = $request->validate([
            'toFacilityId' => ['required', 'exists:facilities,facilityId'],
            'referralType' => ['required', 'in:screening_to_confirmation,confirmation_to_treatment'],
            'notes'        => ['nullable', 'string', 'max:2000'],
        ]);

        $user        = auth('api')->user();
        $fromFacility = Facility::find($user->facilityId);
        $toFacility   = Facility::findOrFail($data['toFacilityId']);

        $referral = match ($data['referralType']) {
            'screening_to_confirmation' => $this->referralService->referToMainHub(
                $client, $fromFacility, $toFacility, $data['notes'] ?? null
            ),
            'confirmation_to_treatment' => $this->referralService->referToTreatment(
                $client, $fromFacility, $toFacility, $data['notes'] ?? null
            ),
        };

        return response()->json([
            'message'  => 'Client referred successfully.',
            'referral' => $referral->load(['fromFacility', 'toFacility']),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();

        $referrals = ClientReferral::with(['client', 'fromFacility', 'toFacility'])
            ->where(function ($q) use ($user) {
                $q->where('toFacilityId', $user->facilityId)
                  ->orWhere('fromFacilityId', $user->facilityId);
            })
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->type, fn($q) => $q->where('referralType', $request->type))
            ->latest()
            ->paginate(20);

        return response()->json($referrals);
    }

    public function updateStatus(Request $request, ClientReferral $referral): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:accepted,completed,declined'],
        ]);

        $referral->update(['status' => $data['status']]);

        return response()->json([
            'message'  => 'Referral status updated.',
            'referral' => $referral,
        ]);
    }
}