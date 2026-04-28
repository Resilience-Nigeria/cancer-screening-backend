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

        // Handle numeric conversions
        if (isset($validated['psaLevel']) && $validated['psaLevel'] !== null) {
            $validated['psaLevel'] = (float) $validated['psaLevel'];
        }
        
        if (isset($validated['ipssScore']) && $validated['ipssScore'] !== null) {
            $validated['ipssScore'] = (int) $validated['ipssScore'];
        }

        // Convert empty strings to null
        $validated = array_map(function ($value) {
            return $value === '' ? null : $value;
        }, $validated);

        $screening = ProstateScreening::updateOrCreate(
            ['visitId' => $visit->visitId],
            [
                ...$validated,
                'visitId' => $visit->visitId,
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
            // CamelCase for frontend
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
            'referral' => $screening->referral,
            
            // Snake_case for backward compatibility
            'psa_level' => $screening->psaLevel,
            'dre_result' => $screening->dreResult,
            'ipss_score' => $screening->ipssScore,
            'poor_urinary_stream' => $screening->poorUrinaryStream,
            'urge_incontinence' => $screening->urgeIncontinence,
            'delay_starting_urination' => $screening->delayStartingUrination,
            'inability_to_hold_urine' => $screening->inabilityToHoldUrine,
            'terminal_dribbling' => $screening->terminalDribbling,
            'frequent_day_urination' => $screening->frequentDayUrination,
            'incomplete_emptying' => $screening->incompleteEmptying,
            'blood_in_urine' => $screening->bloodInUrine,
            'biopsy_done' => (bool) $screening->biopsyDone,
            'gleason_score' => $screening->gleasonScore,
        ];
    }

    /**
     * Authorize that the user can access this visit
     */
    protected function authorizeVisit(ScreeningVisit $visit): void
    {
        $user = auth('api')->user();

        if (!$user->isSuperAdmin() && $visit->facilityId !== $user->facilityId) {
            abort(403, 'You cannot access this visit');
        }
    }
}