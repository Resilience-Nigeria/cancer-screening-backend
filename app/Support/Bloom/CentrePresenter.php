<?php

namespace App\Support\Bloom;

use App\Models\Facility;
use DateTimeInterface;

/**
 * Turns a Facility into the small, public-safe shape the app needs.
 * Staff-only columns (navigator name/phone/email, facility email) are not exposed.
 */
final class CentrePresenter
{
    public static function toArray(Facility $f, ?float $km, DateTimeInterface $now): array
    {
        $days = is_array($f->clinicDays) ? array_values($f->clinicDays) : [];

        $kinds = [];
        if ($f->isScreeningCenter) {
            $kinds[] = 'Screening centre';
        }
        if ($f->isTreatmentCenter) {
            $kinds[] = 'Treatment centre';
        }

        return [
            'id'          => (int) $f->facilityId,
            'name'        => (string) $f->facilityName,
            'address'     => self::address($f),
            'state'       => $f->facilityState,
            'lga'         => $f->facilityLga,
            'lat'         => $f->latitude,
            'lng'         => $f->longitude,
            'distanceKm'  => $km === null ? null : round($km, 1),
            'phone'       => $f->phoneNumber ?: null,
            'whatsapp'    => $f->whatsappNumber ?: null,
            'hours'       => $f->formatClinicHours(),
            'days'        => $days,
            'opensAt'     => $f->clinicOpenTime ?: null,
            'closesAt'    => $f->clinicCloseTime ?: null,
            // null = the centre has not recorded its opening times
            'openNow'     => OpeningHours::isOpen($days, $f->clinicOpenTime, $f->clinicCloseTime, $now),
            'isScreening' => (bool) $f->isScreeningCenter,
            'isTreatment' => (bool) $f->isTreatmentCenter,
            'levels'      => is_array($f->facilityLevels) ? array_values($f->facilityLevels) : [],
            'services'    => self::services($f, $kinds),
        ];
    }

    private static function address(Facility $f): string
    {
        $parts = array_filter([
            trim((string) $f->facilityAddress),
            trim((string) $f->facilityLga),
            trim((string) $f->facilityState),
        ], fn ($p) => $p !== '');

        // Avoid "Jabi, Jabi, FCT" when the address already ends with the LGA/state.
        return implode(', ', array_unique($parts));
    }

    /**
     * Service names when the `services` relation was loaded; otherwise
     * (or when it is empty) the facility types so the card is never blank.
     * Service columns are read defensively because their names are not fixed here.
     */
    private static function services(Facility $f, array $kinds): array
    {
        $names = [];

        if ($f->relationLoaded('services')) {
            foreach ($f->services as $service) {
                $attrs = $service->getAttributes();
                $name  = $attrs['serviceName'] ?? $attrs['name'] ?? $attrs['slug'] ?? null;
                if (is_string($name) && trim($name) !== '') {
                    $names[] = trim($name);
                }
            }
        }

        return array_values(array_unique($names ?: $kinds));
    }
}
