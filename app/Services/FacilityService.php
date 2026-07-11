<?php
// app/Services/FacilityService.php

namespace App\Services;

use App\Models\Facility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FacilityService
{
    public function findNearestScreeningFacility(
        string $state,
        string $lga,
    ): ?Facility {
        // 1. Exact LGA match
        $exact = Facility::whereJsonContains('facilityType', 'sub_hub')
            ->where('facilityState', $state)
            ->where('facilityLga', $lga)
            ->first();

        if ($exact) {
            Log::info('Facility: exact LGA match', [
                'facility' => $exact->facilityName,
                'lga'      => $lga,
            ]);
            return $exact;
        }

        // 2. Get all sub-hubs in the state that have coordinates
        $stateHubs = Facility::whereJsonContains('facilityType', 'sub_hub')
            ->where('facilityState', $state)
            ->get();

        if ($stateHubs->isEmpty()) {
            Log::warning('Facility: no sub-hubs in state', ['state' => $state]);
            return null;
        }

        // 3. Look up the client LGA center coordinates from the reference table
        $lgaCoords = DB::table('lgaCoordinates')
            ->whereRaw('LOWER(state) = ?', [strtolower($state)])
            ->whereRaw('LOWER(lga) = ?',   [strtolower($lga)])
            ->first();

        if ($lgaCoords) {
            // Find closest facility by Haversine distance
            $withCoords = $stateHubs->filter(
                fn($f) => $f->latitude && $f->longitude
            );

            if ($withCoords->isNotEmpty()) {
                $nearest = $withCoords
                    ->sortBy(fn($f) => $this->haversineDistance(
                        $lgaCoords->latitude,  $lgaCoords->longitude,
                        (float) $f->latitude,  (float) $f->longitude,
                    ))
                    ->first();

                Log::info('Facility: nearest by coordinates', [
                    'requested_lga' => $lga,
                    'matched_lga'   => $nearest->facilityLga,
                    'facility'      => $nearest->facilityName,
                ]);

                return $nearest;
            }
        }

        // 4. Final fallback — first facility in state
        $fallback = $stateHubs->first();

        Log::info('Facility: state-level fallback', [
            'state'    => $state,
            'facility' => $fallback?->facilityName,
        ]);

        return $fallback;
    }

    public function haversineDistance(
        float $lat1, float $lng1,
        float $lat2, float $lng2,
    ): float {
        $R    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}