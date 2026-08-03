<?php

namespace App\Services;

use App\Models\Facility;
use App\Models\Navigator;
use Illuminate\Support\Facades\DB;

class NavigatorService
{
    /**
     * Assigns the next navigator in rotation for a facility. Locks the
     * facility row to serialize concurrent assignments so the rotation
     * pointer can't be read/written by two requests at once.
     */
    public function assignNavigator(Facility $facility): ?Navigator
    {
        return DB::transaction(function () use ($facility) {
            $lockedFacility = Facility::where('facilityId', $facility->facilityId)
                ->lockForUpdate()
                ->first();

            $navigators = Navigator::with('user')
                ->where('facilityId', $lockedFacility->facilityId)
                ->where('isActive', true)
                ->orderBy('id')
                ->get();

            if ($navigators->isEmpty()) {
                return null;
            }

            $next = $navigators->first(
                fn ($navigator) => $navigator->id > $lockedFacility->lastAssignedNavigatorId
            ) ?? $navigators->first(); // wrap around to the start

            $lockedFacility->update(['lastAssignedNavigatorId' => $next->id]);

            return $next;
        });
    }
}