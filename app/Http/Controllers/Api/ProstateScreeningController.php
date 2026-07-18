<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProstateScreeningRequest;
use App\Models\ProstateScreening;
use App\Models\ScreeningVisit;
use Illuminate\Http\JsonResponse;

class ProstateScreeningController extends Controller
{
    /**
     * Store or update prostate screening for a visit
     */
    public function store(StoreProstateScreeningRequest $request, ScreeningVisit $visit): JsonResponse
    {
        $this->authorizeVisit($visit);

        $validated = $request->validated();

        // Numeric conversions
        if (isset($validated['psaLevel']) && $validated['psaLevel'] !== null) {
            $validated['psaLevel'] = (float) $validated['psaLevel'];
        }
        if (isset($validated['ipssScore']) && $validated['ipssScore'] !== null) {
            $validated['ipssScore'] = (int) $validated['ipssScore'];
        }

        // Convert empty strings to null
        $validated = array_map(fn ($value) => $value === '' ? null : $value, $validated);

        $screening = ProstateScreening::updateOrCreate(
            ['visitId' => $visit->visitId],
            [
                ...$validated,
                'visitId' => $visit->visitId,
                'clientId' => $visit->clientId,
            ]
        );

        return response()->json([
            'message' => 'Prostate screening saved successfully',
            'data' => $this->formatScreening($screening),
        ], 201);
    }

    /**
     * Get prostate screening for a visit
     */
    public function show(ScreeningVisit $visit): JsonResponse
    {
        $this->authorizeVisit($visit);

        $screening = $visit->prostateScreening;

        return response()->json([
            'data' => $screening ? $this->formatScreening($screening) : null,
        ]);
    }

    /**
     * Format screening data for API response (camelCase for frontend)
     */
    protected function formatScreening(ProstateScreening $screening): array
    {
        return [
            'screeningId' => $screening->screeningId,
            'visitId' => $screening->visitId,
            'clientId' => $screening->clientId,
            'screeningDate' => optional($screening->screeningDate)->toDateString(),
            'screeningResult' => $screening->screeningResult,

            'psaLevel' => $screening->psaLevel,
            'dreResult' => $screening->dreResult,
            'ipssScore' => $screening->ipssScore,

            'poorUrinaryStream' => $screening->poorUrinaryStream,
            'urgeIncontinence' => $screening->urgeIncontinence,
            'delayStartingUrination' => $screening->delayStartingUrination,
            'inabilityToHoldUrine' => $screening->inabilityToHoldUrine,
            'terminalDribbling' => $screening->terminalDribbling,
            'frequentDayUrination' => $screening->frequentDayUrination,
            'nocturia' => $screening->nocturia,
            'incompleteEmptying' => $screening->incompleteEmptying,
            'bloodInUrine' => $screening->bloodInUrine,

            'biopsyDone' => (bool) $screening->biopsyDone,
            'gleasonScore' => $screening->gleasonScore,
            'treatmentReferral' => $screening->treatmentReferral,
            'remarks' => $screening->remarks,
        ];
    }

    /**
     * Authorize that the user can access this visit
     */
    protected function authorizeVisit(ScreeningVisit $visit): void
    {
        $user = auth('api')->user();

        if (!$user->isSuperAdmin() && !$user->isPartner() && $visit->facilityId !== $user->facilityId) {
            abort(403, 'You cannot access this visit');
        }
    }
}