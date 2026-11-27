<?php

namespace App\Observers;

use App\Models\Client;
use App\Models\ScreeningSample;
use App\Models\ScreeningVisit;

/**
 * Register in AppServiceProvider::boot():
 *   \App\Models\ScreeningSample::observe(\App\Observers\SampleFacilityObserver::class);
 *
 * A sample / test belongs to the facility where the screening was done — not to the home facility of
 * whoever typed it in. Without this, an admin or national user recording results (e.g. NICRAT staff based at
 * FMC Jabi) made every sample look like it was taken at their own facility, so the overdue-results pop-up
 * and reports listed hospitals where no screening happened.
 *  - sample from a screening visit  → the visit's facility;
 *  - otherwise, entered by a national/admin user or a user with no facility → the client's facility;
 *  - otherwise (a nurse / navigator at their own facility) → unchanged (their facility);
 *  - booked tests keep the facility they were booked at.
 */
class SampleFacilityObserver
{
    public function creating(ScreeningSample $s): void
    {
        $facility = self::facilityFor($s, auth('api')->user());
        if ($facility) $s->facilityId = $facility;
    }

    public static function facilityFor(ScreeningSample $s, $creator): ?int
    {
        if ($s->status === 'booked') return null;
        if ($s->visitId) {
            $v = ScreeningVisit::where('visitId', $s->visitId)->value('facilityId');
            if ($v) return (int) $v;
        }
        $remote = !$creator || !$creator->facilityId || $creator->visibleFacilityIds() === null
            || (method_exists($creator, 'isSuperAdmin') && ($creator->isSuperAdmin() || $creator->isNicratStaff()));
        if ($remote) {
            $c = Client::where('clientId', $s->clientId)->value('facilityId');
            if ($c) return (int) $c;
        }
        return null;
    }
}
