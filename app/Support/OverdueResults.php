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

    public static function forUser($user, int $days = self::DEFAULT_DAYS, int $limit = 50): array
    {
        $visible = $user->visibleFacilityIds();
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
            'days'       => $days,
            'total'      => $total,
            'byFacility' => $byFacility,
            'samples'    => $samples,
        ];
    }
}
