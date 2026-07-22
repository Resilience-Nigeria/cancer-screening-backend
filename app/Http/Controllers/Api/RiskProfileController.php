<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertRiskProfileRequest;
use App\Models\Client;
use App\Models\ClientRiskProfile;
use App\Services\CancerRiskStratificationService;
use Illuminate\Http\JsonResponse;

class RiskProfileController extends Controller
{
    public function __construct(protected CancerRiskStratificationService $riskModel)
    {
    }

    public function show(Client $client): JsonResponse
    {
        $this->authorizeClient($client);

        return response()->json([
            'risk_profile' => $client->latestRiskProfile,
        ]);
    }

    public function upsert(UpsertRiskProfileRequest $request, Client $client): JsonResponse
    {
        $this->authorizeClient($client);

        $data = $request->validated();
        $data['bmi'] = $this->calculateBmi(
            $data['weightKg'] ?? null,
            $data['heightCm'] ?? null
        );
        $data['recordedAt'] = now();
        $data['recordedBy'] = auth('api')->id();

        // NICRAT Cancer Risk Stratification Model — computed on every
        // save so the record always reflects the current inputs, and
        // stored so it's queryable without recomputing.
        $classification = $this->riskModel->classify(
            $data['bmi'],
            $data['smokingStatus'] ?? null,
            $data['alcoholConsumption'] ?? null,
            $data['physicalActivityLevel'] ?? null,
            $data['hivStatus'] ?? null,
        );
        $data = [...$data, ...$classification];

        $socioeconomic = $this->riskModel->classifySocioeconomicStatus($data['occupationCategory'] ?? null);
        if ($socioeconomic) {
            $data = [...$data, ...$socioeconomic];
        }

        $riskProfile = ClientRiskProfile::updateOrCreate(
            ['clientId' => $client->clientId],
            [
                ...$data,
                'clientId' => $client->clientId,
            ]
        );

        return response()->json([
            'message' => 'Risk profile saved successfully',
            'risk_profile' => $riskProfile,
        ]);
    }

    protected function calculateBmi($weightKg, $heightCm): ?float
    {
        if (!$weightKg || !$heightCm || $heightCm <= 0) {
            return null;
        }

        $heightM = $heightCm / 100;
        return round($weightKg / ($heightM * $heightM), 2);
    }

    protected function authorizeClient(Client $client): void
    {
        $user = auth('api')->user();

        if (!$user->canAccessFacility($client->facilityId)) {
            abort(403, 'You cannot access this client');
        }
    }
}
