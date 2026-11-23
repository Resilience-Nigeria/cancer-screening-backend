<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One set of referral rules for the dashboard, the analytics page and the Referred Clients page.
 *
 *  - A referral RECORD is a row in client_referrals (made when a Stage 2 outcome is suspicious, or by hand).
 *    Same-facility rows count too: the client continues at a Stage 3-capable facility.
 *  - A FORM referral is a screening module with treatmentReferral = 'referred'. 'not_referred' is NOT a referral.
 *  - "Form only" = marked Referred on the form, but the client has no referral record at all.
 */
class Referrals
{
    public const MODULES = [
        'cervical'   => 'cervical_screenings',
        'breast'     => 'breast_screenings',
        'prostate'   => 'prostate_screenings',
        'colorectal' => 'colorectal_screenings',
        'liver'      => 'liver_screenings',
    ];

    /**
     * Screening modules marked "Referred", one row per module record:
     * clientId, cancerType, screeningDate, facilityId, fullName, phoneNumber, email.
     * $facilities null = every facility.
     */
    public static function formReferrals(?array $facilities, ?string $from = null, ?string $to = null, string $search = ''): Collection
    {
        $rows = collect();
        foreach (self::MODULES as $type => $table) {
            try {
                $date = "COALESCE({$table}.screeningDate, screening_visits.visitDate)";
                $rows = $rows->merge(
                    DB::table($table)
                        ->join('screening_visits', "{$table}.visitId", '=', 'screening_visits.visitId')
                        ->join('clients', 'screening_visits.clientId', '=', 'clients.clientId')
                        ->where("{$table}.treatmentReferral", 'referred')
                        ->when($facilities !== null, fn ($q) => $q->whereIn('clients.facilityId', $facilities))
                        ->when($from, fn ($q) => $q->whereDate(DB::raw($date), '>=', $from))
                        ->when($to, fn ($q) => $q->whereDate(DB::raw($date), '<=', $to))
                        ->when($search !== '', function ($q) use ($search) {
                            $q->where(fn ($w) => $w->where('clients.clientId', 'like', "%{$search}%")
                                ->orWhere('clients.fullName', 'like', "%{$search}%")
                                ->orWhere('clients.phoneNumber', 'like', "%{$search}%"));
                        })
                        ->selectRaw("clients.clientId, '{$type}' as cancerType, DATE({$date}) as screeningDate,
                                     clients.facilityId, clients.fullName, clients.phoneNumber, clients.email")
                        ->get()
                );
            } catch (\Throwable $e) {
                // module table or column missing — skip it
            }
        }
        return $rows;
    }

    /** Clients with a referral record sent from these facilities (null = all). */
    public static function recordedSentClientIds(?array $facilities, ?string $from = null, ?string $to = null): Collection
    {
        return DB::table('client_referrals')
            ->join('clients', 'client_referrals.clientId', '=', 'clients.clientId')
            ->when($facilities !== null, function ($q) use ($facilities) {
                $q->where(function ($w) use ($facilities) {
                    $w->whereIn('client_referrals.fromFacilityId', $facilities)
                      // older rows without a sending facility belong to the client's own facility
                      ->orWhere(fn ($x) => $x->whereNull('client_referrals.fromFacilityId')->whereIn('clients.facilityId', $facilities));
                });
            })
            ->when($from, fn ($q) => $q->whereDate('client_referrals.referralDate', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('client_referrals.referralDate', '<=', $to))
            ->distinct()
            ->pluck('client_referrals.clientId');
    }

    /** Form referrals whose client has no referral record at all, one row per client (latest screening). */
    public static function formOnly(?array $facilities, ?string $from = null, ?string $to = null, string $search = ''): Collection
    {
        $form = self::formReferrals($facilities, $from, $to, $search);
        if ($form->isEmpty()) {
            return collect();
        }
        $recorded = DB::table('client_referrals')
            ->whereIn('clientId', $form->pluck('clientId')->unique()->values())
            ->distinct()->pluck('clientId')->flip();

        return $form->reject(fn ($r) => isset($recorded[$r->clientId]))
            ->groupBy('clientId')
            ->map(function ($rows) {
                $latest = $rows->sortByDesc('screeningDate')->first();
                $latest->cancerTypes = $rows->pluck('cancerType')->unique()->values()->all();
                return $latest;
            })
            ->sortByDesc('screeningDate')
            ->values();
    }

    /** Distinct clients referred from these facilities: referral records + form referrals. */
    public static function referredClientCount(?array $facilities, ?string $from = null, ?string $to = null): int
    {
        return self::recordedSentClientIds($facilities, $from, $to)
            ->merge(self::formReferrals($facilities, $from, $to)->pluck('clientId'))
            ->unique()
            ->count();
    }
}
