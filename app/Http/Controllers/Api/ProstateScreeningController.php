<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProstateScreeningRequest;
use App\Models\ProstateScreening;
use App\Models\ScreeningVisit;
use Illuminate\Http\JsonResponse;

class ProstateScreeningController extends Controller
{
    public function store(StoreProstateScreeningRequest $request, ScreeningVisit $visit): JsonResponse
    {
        $this->authorizeVisit($visit);

        $screening = ProstateScreening::updateOrCreate(
            ['visitId' => $visit->visitId],
            [
                ...$request->validated(),
                'visitId' => $visit->visitId,
            ]
        );

        return response()->json([
            'message' => 'Prostate screening saved successfully',
            'screening' => $screening,
        ], 201);
    }

    public function show(ScreeningVisit $visit): JsonResponse
    {
        $this->authorizeVisit($visit);

        return response()->json([
            'screening' => $visit->prostateScreening,
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