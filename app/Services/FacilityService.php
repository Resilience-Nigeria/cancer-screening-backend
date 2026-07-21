<?php
// app/Services/FacilityService.php

namespace App\Services;

use App\Models\Facility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FacilityService
{
    /**
     * Find the nearest screening facility — any tier (Feeder, SubHub, or
     * Hub) can be a screening center, so this matches on the
     * isScreeningCenter flag rather than a specific hierarchy level.
     *
     * Priority:
     * 1. Exact LGA match in same state
     * 2. Nearest facility in same state by coordinates
     * 3. Nearest facility in any state within radius (cross-state)
     * 4. Any facility in same state (no-coordinates fallback)
     * 5. null
     */
  public function findNearestScreeningFacility(
    string $state,
    string $lga,
    ?string $area = null,   // 👈 new optional parameter
): ?Facility {
    // ── Step 1: Exact LGA + state match ──────────────────────────────
    $exact = Facility::where('isScreeningCenter', true)
        ->where('isActive', true)
        ->where('facilityState', $state)
        ->where('facilityLga', $lga)
        ->first();

    if ($exact) {
        Log::info('Facility: exact LGA match', [
            'facility' => $exact->facilityName,
        ]);
        return $exact;
    }

    // ── Get coordinates — area first, then LGA center ─────────────────
    $coords = null;

    // Try area/district level first (more precise)
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

    // Fall back to LGA center coordinates
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

    // ── Step 2: Distance-based search across all active facilities ────
    if ($coords) {
        $clientLat = (float) $coords->latitude;
        $clientLng = (float) $coords->longitude;

        $allFacilities = Facility::where('isScreeningCenter', true)
            ->where('isActive', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->get();

        if ($allFacilities->isNotEmpty()) {
            $nearest = $allFacilities
                ->map(function ($f) use ($clientLat, $clientLng) {
                    $f->distanceKm = $this->haversineDistance(
                        $clientLat, $clientLng,
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

    // ── Step 3: No coordinates — same state fallback ──────────────────
    $fallback = Facility::where('isScreeningCenter', true)
        ->where('isActive', true)
        ->where('facilityState', $state)
        ->first();

    if ($fallback) {
        Log::info('Facility: state-level fallback', [
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

    /**
     * Find the nearest SubHub or Hub facility for referral — used when a
     * Stage 2 screening outcome is "suspicious" or "urgent_referral" and
     * the client needs to be linked onward for confirmation/diagnostic
     * workup or treatment. Feeders are excluded here since escalation
     * always goes up the hierarchy (Feeder -> SubHub -> Hub), never to
     * another Feeder.
     *
     * Same priority order as findNearestScreeningFacility, but filtered
     * to facilityLevel in [subhub, hub] instead of sub-hub type.
     */
    public function findNearestReferralFacility(
        string $state,
        string $lga,
        ?string $area = null,
    ): ?Facility {
        $levelFilter = fn ($q) => $q->whereIn('facilityLevel', ['subhub', 'hub']);

        // ── Step 1: Exact LGA + state match ──────────────────────────
        $exact = Facility::where($levelFilter)
            ->where('isActive', true)
            ->where('facilityState', $state)
            ->where('facilityLga', $lga)
            ->orderByRaw("FIELD(facilityLevel, 'hub', 'subhub')")
            ->first();

        if ($exact) {
            Log::info('Referral facility: exact LGA match', ['facility' => $exact->facilityName]);
            return $exact;
        }

        // ── Get coordinates — area first, then LGA center ─────────────
        $coords = null;

        if ($area) {
            $coords = DB::table('areaCoordinates')
                ->whereRaw('LOWER(state) = ?', [strtolower($state)])
                ->whereRaw('LOWER(lga) = ?', [strtolower($lga)])
                ->whereRaw('LOWER(area) = ?', [strtolower($area)])
                ->first();
        }

        if (!$coords) {
            $coords = DB::table('lgaCoordinates')
                ->whereRaw('LOWER(state) = ?', [strtolower($state)])
                ->whereRaw('LOWER(lga) = ?', [strtolower($lga)])
                ->first();
        }

        // ── Step 2: Distance-based search across all active facilities ──
        if ($coords) {
            $clientLat = (float) $coords->latitude;
            $clientLng = (float) $coords->longitude;

            $allFacilities = Facility::where($levelFilter)
                ->where('isActive', true)
                ->whereNotNull('latitude')
                ->whereNotNull('longitude')
                ->get();

            if ($allFacilities->isNotEmpty()) {
                $nearest = $allFacilities
                    ->map(function ($f) use ($clientLat, $clientLng) {
                        $f->distanceKm = $this->haversineDistance(
                            $clientLat, $clientLng,
                            (float) $f->latitude,
                            (float) $f->longitude,
                        );
                        return $f;
                    })
                    ->sortBy('distanceKm')
                    ->first();

                Log::info('Referral facility: nearest by distance', [
                    'facility' => $nearest->facilityName,
                    'level' => $nearest->facilityLevel,
                    'distance_km' => round($nearest->distanceKm, 2),
                ]);

                return $nearest;
            }
        }

        // ── Step 3: No coordinates — same state fallback ────────────────
        $fallback = Facility::where($levelFilter)
            ->where('isActive', true)
            ->where('facilityState', $state)
            ->orderByRaw("FIELD(facilityLevel, 'hub', 'subhub')")
            ->first();

        if ($fallback) {
            Log::info('Referral facility: state-level fallback', ['facility' => $fallback->facilityName]);
            return $fallback;
        }

        Log::warning('Referral facility: no active SubHub/Hub match found', [
            'state' => $state,
            'lga' => $lga,
            'area' => $area,
        ]);

        return null;
    }

    /**
     * Great-circle distance between two lat/lng points, in kilometers.
     * Pure math — no branch here can return anything but a float, so this
     * can't produce the "must be of type float, null returned" error that
     * a buggy version (e.g. one with an early `return null;` for missing
     * coordinates) would.
     */
    protected function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}