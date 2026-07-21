<?php

namespace App\Services;

use App\Models\Facility;
use Illuminate\Support\Facades\Log;

/**
 * Resolves where a client should go next for a given patient-journey
 * stage (e.g. "stage3" after a suspicious/urgent Stage 2 outcome).
 *
 * This is deliberately NOT hardcoded to facilityLevel — a facility's
 * stage capabilities live in the stagesSupported column, and the
 * escalation path follows the actual parentFacilityId hierarchy an
 * admin has configured (Feeder -> SubHub -> Hub), not a geography
 * search. If the client's current facility already supports the
 * needed stage, they stay there — no referral to elsewhere is needed.
 */
class ReferralRoutingService
{
    protected const MAX_HIERARCHY_DEPTH = 10; // guards against a misconfigured circular parent chain

    /**
     * @return array{facility: ?Facility, isSelfReferral: bool}
     */
    public function resolveForStage(Facility $currentFacility, string $stage): array
    {
        if ($currentFacility->supportsStage($stage)) {
            Log::info('Referral routing: current facility already supports stage', [
                'facility' => $currentFacility->facilityName,
                'stage' => $stage,
            ]);

            return ['facility' => $currentFacility, 'isSelfReferral' => true];
        }

        $facility = $currentFacility;
        $depth = 0;

        while ($facility->parentFacilityId && $depth < self::MAX_HIERARCHY_DEPTH) {
            $facility = $facility->parentFacility;
            $depth++;

            if (!$facility) {
                break;
            }

            if ($facility->supportsStage($stage)) {
                Log::info('Referral routing: found capable ancestor', [
                    'from' => $currentFacility->facilityName,
                    'to' => $facility->facilityName,
                    'stage' => $stage,
                    'hops' => $depth,
                ]);

                return ['facility' => $facility, 'isSelfReferral' => false];
            }
        }

        Log::warning('Referral routing: no capable facility found in hierarchy', [
            'from' => $currentFacility->facilityName,
            'stage' => $stage,
        ]);

        return ['facility' => null, 'isSelfReferral' => false];
    }
}
