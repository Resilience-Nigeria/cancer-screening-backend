<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBreastScreeningRequest;
use App\Models\BreastScreening;
use App\Models\ScreeningVisit;
use Illuminate\Http\JsonResponse;

class BreastScreeningController extends Controller
{
    public function store(StoreBreastScreeningRequest $request, ScreeningVisit $visit): JsonResponse
    {
        $this->authorizeVisit($visit);

        $screening = BreastScreening::updateOrCreate(
            ['visitId' => $visit->visitId],
            [
                ...$request->validated(),
                'visitId' => $visit->visitId,
                'clientId' => $visit->clientId,
            ]
        );

        return response()->json([
            'message' => 'Breast screening saved successfully',
            'screening' => $screening,
        ], 201);
    }

    public function show(ScreeningVisit $visit): JsonResponse
    {
        $this->authorizeVisit($visit);

        return response()->json([
            'screening' => $visit->breastScreening,
        ]);
    }

    protected function authorizeVisit(ScreeningVisit $visit): void
    {
        $user = auth('api')->user();

        if (!$user->isSuperAdmin() && $visit->facilityId !== $user->facilityId) {
            abort(403, 'You cannot access this visit');
        }
    }
}