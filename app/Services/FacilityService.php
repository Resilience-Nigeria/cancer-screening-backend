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
        ?string $area = null,
    ): ?Facility {
        // ── Step 1: Exact LGA + state match ──────────────────────────
        $exact = Facility::whereJsonContains('facilityType', 'sub_hub')
            ->where('isActive', true)
            ->where('facilityState', $state)
            ->where('facilityLga', $lga)
            ->first();

        if ($exact) {
            Log::info('Facility: exact LGA match', [
                'facility' => $exact->facilityName,
                'lga'      => $lga,
                'state'    => $state,
            ]);
            return $exact;
        }

        // ── Get coordinates — area first, then LGA center ─────────────
        $coords = null;

        if ($area) {
            $coords = DB::table('areaCoordinates')
                ->whereRaw('LOWER(state) = ?', [strtolower($state)])
                ->whereRaw('LOWER(lga) = ?',   [strtolower($lga)])
                ->whereRaw('LOWER(area) = ?',  [strtolower($area)])
                ->first();

            if ($coords) {
                Log::info('Coordinates: matched by area', [
                    'area' => $area,
                    'lat'  => $coords->latitude,
                    'lng'  => $coords->longitude,
                ]);
            }
        }

        if (!$coords) {
            $coords = DB::table('lgaCoordinates')
                ->whereRaw('LOWER(state) = ?', [strtolower($state)])
                ->whereRaw('LOWER(lga) = ?',   [strtolower($lga)])
                ->first();

            if ($coords) {
                Log::info('Coordinates: matched by LGA center', [
                    'lga' => $lga,
                    'lat' => $coords->latitude,
                    'lng' => $coords->longitude,
                ]);
            }
        }

        // ── Step 2: Distance-based search across all active facilities ─
        if ($coords) {
            $clientLat = (float) $coords->latitude;
            $clientLng = (float) $coords->longitude;

            $allFacilities = Facility::whereJsonContains('facilityType', 'sub_hub')
                ->where('isActive', true)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->get();

            if ($allFacilities->isNotEmpty()) {
                $nearest = $allFacilities
                    ->map(function ($f) use ($clientLat, $clientLng) {
                        $f->distanceKm = $this->haversineDistance(
                            $clientLat,
                            $clientLng,
                            (float) $f->latitude,
                            (float) $f->longitude,
                        );
                        return $f;
                    })
                    ->sortBy('distanceKm')
                    ->first();

                Log::info('Facility: nearest by distance', [
                    'facility'    => $nearest->facilityName,
                    'state'       => $nearest->facilityState,
                    'lga'         => $nearest->facilityLga,
                    'distance_km' => round($nearest->distanceKm, 2),
                    'cross_state' => $nearest->facilityState !== $state,
                ]);

                return $nearest;
            }
        }

        // ── Step 3: No coordinates — same state fallback ───────────────
        $fallback = Facility::whereJsonContains('facilityType', 'sub_hub')
            ->where('isActive', true)
            ->where('facilityState', $state)
            ->first();

        if ($fallback) {
            Log::info('Facility: state-level fallback', [
                'state'    => $state,
                'facility' => $fallback->facilityName,
            ]);
            return $fallback;
        }

        Log::warning('Facility: no active match found', [
            'state' => $state,
            'lga'   => $lga,
            'area'  => $area,
        ]);

        return null;
    }

    public function haversineDistance(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2,
    ): float {
        $R    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
           + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}