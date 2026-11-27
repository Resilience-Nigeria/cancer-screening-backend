<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BreastScreening;
use App\Models\Client;
use App\Models\ScreeningSample;
use App\Models\ScreeningVisit;
use Illuminate\Http\JsonResponse;

/**
 * POST /visits/{visitId}/breast-sync
 *
 * Simulation report §3: the CBE recorded in the screening form also appears under Samples & Test Results
 * (as the visit's "Clinical Breast Exam" test with its result), so staff don't register it again there and
 * don't get a second CBE number for the same exam. Called by the web and mobile screening forms after
 * the breast screening is saved; safe to call repeatedly (it updates the same test record).
 */
class BreastSyncController extends Controller
{
    private const LABEL = [
        'nipple_discharge' => 'Nipple discharge', 'nipple_retraction' => 'Nipple retraction', 'skin_discoloration' => 'Skin discoloration',
        'skin_dimpling' => 'Skin dimpling', 'skin_thickening' => 'Skin thickening', 'skin_ulceration' => 'Skin ulceration',
        'visible_veins' => 'Visible / distended veins', 'asymmetry' => 'Breast asymmetry', 'swelling' => 'Breast swelling', 'other' => 'Other change',
    ];

    public function sync(int $visitId): JsonResponse
    {
        $user = auth('api')->user();
        $visit = ScreeningVisit::where('visitId', $visitId)->firstOrFail();
        $client = Client::where('clientId', $visit->clientId)->firstOrFail();
        abort_if(method_exists($user, 'canAccessFacility') && !$user->canAccessFacility($client->facilityId), 403, 'You cannot access this client');

        $b = BreastScreening::where('visitId', $visitId)->orderByDesc('screeningId')->first();
        $cbeDone = $b && ($b->leftCbeResult || $b->rightCbeResult || $b->leftCbeFinding || $b->rightCbeFinding);
        if (!$cbeDone) return response()->json(['synced' => false, 'message' => 'No CBE recorded for this visit.']);

        $suspicious = in_array('suspicious', [$b->leftCbeFinding, $b->rightCbeFinding], true)
            || in_array('lump_detected', [$b->leftCbeResult, $b->rightCbeResult], true);
        $changes = collect((array) ($b->physicalChanges ?? []))->map(fn ($c) => self::LABEL[$c] ?? $c)->implode(', ');
        $meta = array_filter([
            'leftCbeResult' => $b->leftCbeResult, 'rightCbeResult' => $b->rightCbeResult,
            'leftCbeFinding' => $b->leftCbeFinding, 'rightCbeFinding' => $b->rightCbeFinding,
            'breastLumps' => $b->breastLumps, 'breastNippleDischarge' => $b->breastNippleDischarge, 'dischargeType' => $b->dischargeType,
            'skinChanges' => $b->skinChanges, 'breastPain' => $b->breastPain,
            'previousBiopsy' => $b->previousBiopsy,
        ], fn ($v) => $v !== null && $v !== '');
        $date = optional($b->screeningDate)->toDateString() ?? optional($visit->visitDate)->toDateString() ?? now()->toDateString();

        // The same visit's CBE test if it exists — or one recorded by hand on the same day — else a new one.
        $sample = ScreeningSample::where('visitId', $visitId)->where('testType', 'cbe')->first()
            ?? ScreeningSample::where('clientId', $client->clientId)->where('testType', 'cbe')->whereNull('visitId')
                ->whereDate('sampleDate', $date)->first()
            ?? new ScreeningSample([
                'clientId' => $client->clientId, 'facilityId' => $visit->facilityId ?? $client->facilityId,
                'cancerType' => 'breast', 'testType' => 'cbe', 'sampleDate' => $date,
                'collectedById' => $user->id, 'status' => 'collected',
            ]);

        $sample->fill([
            'visitId' => $visitId,
            'result' => $suspicious ? 'suspicious' : 'normal',
            'resultSeverity' => $suspicious ? 'urgent' : 'normal',
            'resultMeta' => $meta ?: null,
            'resultDate' => $date,
            'resultNotes' => trim(implode('. ', array_filter([
                $changes ? "Physical changes: {$changes}" : null,
                $b->physicalChangesOther ? "Other: {$b->physicalChangesOther}" : null,
                $b->recommendedNextStep ? 'Recommended next: ' . str_replace('_', ' ', $b->recommendedNextStep) : null,
            ]))) ?: null,
            'status' => 'result_released',
            'resultEnteredById' => $user->id,
        ]);
        $sample->save();

        return response()->json(['synced' => true, 'sample' => $sample->fresh()]);
    }
}
