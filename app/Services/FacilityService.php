<?php

namespace App\Services;

use App\Models\Facility;

class FacilityService
{
    /**
     * Find the nearest sub-hub screening facility
     * matching the client's state and LGA.
     * Falls back to state-only if no LGA match exists.
     */
    public function findNearestScreeningFacility(
        string $state,
        string $lga
    ): ?Facility {
        // Exact LGA match first
        $facility = Facility::whereJsonContains('facilityType', 'sub_hub')
            ->where('facilityState', $state)
            ->where('facilityLga', $lga)
            ->first();

        // Fall back to state-only
        if (!$facility) {
            $facility = Facility::whereJsonContains('facilityType', 'sub_hub')
                ->where('facilityState', $state)
                ->first();
        }

        return $facility;
    }
}