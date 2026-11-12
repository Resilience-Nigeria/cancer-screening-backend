<?php

namespace App\Support\Bloom;

final class Geo
{
    private const EARTH_KM = 6371.0088;

    /** Great-circle distance in kilometres (haversine). */
    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $h = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return self::EARTH_KM * 2 * asin(min(1.0, sqrt($h)));
    }

    /**
     * A square that contains the circle, used to cut the SQL rows down before
     * the exact distance is worked out.
     *
     * @return array{minLat: float, maxLat: float, minLng: float, maxLng: float}
     */
    public static function boundingBox(float $lat, float $lng, float $radiusKm): array
    {
        $dLat = rad2deg($radiusKm / self::EARTH_KM);
        $cos  = max(0.01, cos(deg2rad($lat)));            // avoid dividing by ~0 at the poles
        $dLng = rad2deg($radiusKm / (self::EARTH_KM * $cos));

        return [
            'minLat' => $lat - $dLat,
            'maxLat' => $lat + $dLat,
            'minLng' => $lng - $dLng,
            'maxLng' => $lng + $dLng,
        ];
    }
}
