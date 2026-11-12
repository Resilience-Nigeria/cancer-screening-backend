<?php

namespace App\Support;

use App\Models\ScreeningSample;

/**
 * Samples / tests that are still waiting for a result after a number of days (default 7).
 * Used by the admin pop-up (GET /screening-samples/overdue) and the daily email reminder.
 */
class OverdueResults
{
    public const DEFAULT_DAYS = 7;

    public static function isAdmin($user): bool
    {
        return $user
            && ($user->isSuperAdmin()
                || $user->isNicratStaff()
                || (method_exists($user, 'isHospitalAdminRole') && $user->isHospitalAdminRole()));
    }

    /** Super admins and NICRAT admins see every facility. */
    public static function isNational($user): bool
    {
        return $user && ($user->isSuperAdmin() || $user->isNicratStaff());
    }

    /**
     * Facilities whose overdue results this user sees:
     *  - Super Admin / NICRAT Admin: all (null = no limit)
     *  - Hospital Admin: the facilities they can access
     *  - everyone else (desk officers, nurses, ...): only their own facility
     */
    public static function facilitiesFor($user): ?array
    {
        if (self::isNational($user)) return null;
        if (self::isAdmin($user)) return $user->visibleFacilityIds();
        return $user->facilityId ? [(int) $user->facilityId] : [];
    }

    public static function scopeLabel($user): string
    {
        if (self::isNational($user)) return 'all';
        if (self::isAdmin($user)) return 'facilities';
        return 'facility';
    }

    public static function forUser($user, int $days = self::DEFAULT_DAYS, int $limit = 50): array
    {
        $visible = self::facilitiesFor($user);
        $cutoff = now()->subDays($days)->toDateString();

        $base = ScreeningSample::query()
            ->where('status', 'collected')
            ->whereDate('sampleDate', '<=', $cutoff)
            ->when($visible !== null, fn ($q) => $q->whereIn('facilityId', $visible));

        $total = (clone $base)->count();

        $byFacility = (clone $base)
            ->selectRaw('facilityId, COUNT(*) as n, MIN(sampleDate) as oldest')
            ->groupBy('facilityId')
            ->orderByDesc('n')
            ->with('facility:facilityId,facilityName,facilityState')
            ->get()
            ->map(fn ($r) => [
                'facilityId'    => $r->facilityId,
                'facilityName'  => $r->facility->facilityName ?? 'Unknown facility',
                'facilityState' => $r->facility->facilityState ?? null,
                'count'         => (int) $r->n,
                'oldest'        => $r->oldest ? substr((string) $r->oldest, 0, 10) : null,
            ])->values()->all();

        $samples = (clone $base)
            ->with(['client:clientId,fullName,phoneNumber', 'facility:facilityId,facilityName'])
            ->orderBy('sampleDate')
            ->limit($limit)
            ->get()
            ->map(fn ($s) => [
                'sampleId'    => $s->sampleId,
                'sampleRef'   => $s->sampleRef,
                'testType'    => $s->testType,
                'testLabel'   => ScreeningTestCatalog::find($s->testType)['label'] ?? $s->testType,
                'clientId'    => $s->clientId,
                'clientName'  => $s->client->fullName ?? null,
                'facility'    => $s->facility->facilityName ?? null,
                'sampleDate'  => $s->sampleDate ? $s->sampleDate->toDateString() : null,
                'daysWaiting' => $s->sampleDate ? (int) $s->sampleDate->diffInDays(now()) : null,
                'collectedBy' => $s->collectedBy,
            ])->values()->all();

        return [
            'scope'      => self::scopeLabel($user),
            'days'       => $days,
            'total'      => $total,
            'byFacility' => $byFacility,
            'samples'    => $samples,
        ];
    }
}
