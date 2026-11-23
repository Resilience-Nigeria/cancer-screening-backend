<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Figures for the weekly management summary, for one set of facilities (null = all).
 * Uses the same rules as the dashboard, Analytics and Screened Clients (ClientFindings, Referrals),
 * so the numbers in the email match the system.
 */
class WeeklySummary
{
    /** Default period: the 7 days ending today (Lagos time). */
    public static function defaultPeriod(): array
    {
        $to = now(\App\Models\ReportSetting::TIMEZONE)->startOfDay();
        return [$to->copy()->subDays(6), $to];
    }

    public static function build(?array $facilities, Carbon $from, Carbon $to): array
    {
        $f = $from->toDateString();
        $t = $to->toDateString();
        $days = $from->diffInDays($to) + 1;
        $pf = $from->copy()->subDays($days)->toDateString();
        $pt = $from->copy()->subDay()->toDateString();

        $now = ClientFindings::build($facilities, $f, $t);
        $prev = ClientFindings::build($facilities, $pf, $pt);
        $susp = fn (array $set) => array_keys(array_filter($set, fn ($c) => ClientFindings::isSuspicious($c)));

        $samples = fn () => DB::table('screening_samples')
            ->when(Schema::hasColumn('screening_samples', 'deletedAt'), fn ($q) => $q->whereNull('deletedAt'))
            ->when($facilities !== null, fn ($q) => $q->whereIn('facilityId', $facilities));

        $registered = fn ($a, $b) => DB::table('clients')
            ->when($facilities !== null, fn ($q) => $q->whereIn('facilityId', $facilities))
            ->whereBetween(DB::raw('DATE(registrationDate)'), [$a, $b])->count();
        $taken = fn ($a, $b) => $samples()->whereIn('status', ['collected', 'result_released'])
            ->whereBetween(DB::raw('DATE(sampleDate)'), [$a, $b])->count();
        $released = fn ($a, $b) => $samples()->where('status', 'result_released')
            ->whereBetween(DB::raw('DATE(resultDate)'), [$a, $b])->count();

        $suspNow = $susp($now);

        $kpis = [
            ['key' => 'registered', 'label' => 'New clients registered', 'value' => $registered($f, $t), 'previous' => $registered($pf, $pt)],
            ['key' => 'screened', 'label' => 'Clients screened', 'value' => count(ClientFindings::screenedIds($now)), 'previous' => count(ClientFindings::screenedIds($prev))],
            ['key' => 'suspicious', 'label' => 'Suspicious findings', 'value' => count($suspNow), 'previous' => count($susp($prev))],
            ['key' => 'referred', 'label' => 'Clients referred', 'value' => Referrals::referredClientCount($facilities, $f, $t), 'previous' => Referrals::referredClientCount($facilities, $pf, $pt)],
            ['key' => 'samples', 'label' => 'Samples / tests taken', 'value' => $taken($f, $t), 'previous' => $taken($pf, $pt)],
            ['key' => 'results', 'label' => 'Results released', 'value' => $released($f, $t), 'previous' => $released($pf, $pt)],
        ];

        // By cancer type
        $byType = [];
        foreach (array_keys(ClientFindings::MODULES) as $type) {
            $screened = 0;
            $s = 0;
            foreach ($now as $c) {
                if (!array_key_exists($type, $c['types'])) continue;
                $screened++;
                if ($c['types'][$type] === 'suspicious') $s++;
            }
            if ($screened) $byType[] = ['cancerType' => ucfirst($type), 'screened' => $screened, 'suspicious' => $s];
        }

        // Lab
        $overdueCut = now()->subDays(OverdueResults::DEFAULT_DAYS)->toDateString();
        $turn = $samples()->where('status', 'result_released')->whereNotNull('sampleDate')
            ->whereBetween(DB::raw('DATE(resultDate)'), [$f, $t])->get(['sampleDate', 'resultDate'])
            ->map(fn ($r) => max(0, (int) floor((strtotime(substr($r->resultDate, 0, 10)) - strtotime(substr($r->sampleDate, 0, 10))) / 86400)));
        $lab = [
            'awaiting' => $samples()->where('status', 'collected')->count(),
            'overdue' => $samples()->where('status', 'collected')->whereDate('sampleDate', '<=', $overdueCut)->count(),
            'avgTurnaroundDays' => $turn->count() ? round($turn->avg(), 1) : null,
        ];

        // Referrals
        $refBase = fn () => DB::table('client_referrals')
            ->join('clients', 'client_referrals.clientId', '=', 'clients.clientId')
            ->whereNotNull('client_referrals.toFacilityId')
            ->when($facilities !== null, fn ($q) => $q->whereIn('client_referrals.toFacilityId', $facilities));
        $referrals = [
            'open' => $refBase()->whereIn('client_referrals.status', ['pending', 'accepted'])->count(),
            'waitingOver14' => $refBase()->where('client_referrals.status', 'pending')
                ->whereDate('client_referrals.referralDate', '<=', now()->subDays(14)->toDateString())->count(),
            'completedThisWeek' => Schema::hasColumn('client_referrals', 'updated_at')
                ? $refBase()->where('client_referrals.status', 'completed')->whereBetween(DB::raw('DATE(client_referrals.updated_at)'), [$f, $t])->count()
                : null,
            'formOnly' => Referrals::formOnly($facilities)->count(),
        ];

        // Suspicious this week with no referral yet
        $notReferred = 0;
        if ($suspNow) {
            $ref = collect();
            foreach (array_chunk($suspNow, 1000) as $chunk) {
                $ref = $ref->merge(DB::table('client_referrals')->whereIn('clientId', $chunk)->distinct()->pluck('clientId'));
            }
            $ref = $ref->merge(Referrals::formReferrals($facilities)->pluck('clientId'))->unique()->flip();
            $notReferred = count(array_filter($suspNow, fn ($id) => !isset($ref[$id])));
        }

        // Treatment
        $treatment = ['active' => null, 'newThisWeek' => null];
        if (Schema::hasTable('treatment_plans')) {
            $tp = fn () => DB::table('treatment_plans')->when($facilities !== null, fn ($q) => $q->whereIn('facilityId', $facilities));
            $treatment['active'] = $tp()->where('status', 'active')->count();
            if (Schema::hasColumn('treatment_plans', 'created_at')) {
                $treatment['newThisWeek'] = $tp()->whereBetween(DB::raw('DATE(created_at)'), [$f, $t])->count();
            }
        }

        // Facilities this week
        $rows = [];
        foreach ($now as $c) {
            if (empty($c['types']) || !$c['facilityId']) continue;
            $fid = $c['facilityId'];
            $rows[$fid] ??= ['facilityId' => $fid, 'screened' => 0, 'suspicious' => 0, 'samples' => 0, 'overdue' => 0];
            $rows[$fid]['screened']++;
            if (ClientFindings::isSuspicious($c)) $rows[$fid]['suspicious']++;
        }
        foreach ($samples()->whereIn('status', ['collected', 'result_released'])->whereBetween(DB::raw('DATE(sampleDate)'), [$f, $t])
                     ->selectRaw('facilityId, COUNT(*) as n')->groupBy('facilityId')->get() as $r) {
            if (!$r->facilityId) continue;
            $rows[$r->facilityId] ??= ['facilityId' => $r->facilityId, 'screened' => 0, 'suspicious' => 0, 'samples' => 0, 'overdue' => 0];
            $rows[$r->facilityId]['samples'] = (int) $r->n;
        }
        foreach ($samples()->where('status', 'collected')->whereDate('sampleDate', '<=', $overdueCut)
                     ->selectRaw('facilityId, COUNT(*) as n')->groupBy('facilityId')->get() as $r) {
            if (!$r->facilityId) continue;
            $rows[$r->facilityId] ??= ['facilityId' => $r->facilityId, 'screened' => 0, 'suspicious' => 0, 'samples' => 0, 'overdue' => 0];
            $rows[$r->facilityId]['overdue'] = (int) $r->n;
        }

        // Facilities that screened in the 4 weeks before this period but not in it
        $earlier = ClientFindings::build($facilities, $from->copy()->subDays(28)->toDateString(), $pt);
        $activeBefore = [];
        foreach ($earlier as $c) {
            if (!empty($c['types']) && $c['facilityId']) $activeBefore[$c['facilityId']] = true;
        }
        $quietIds = array_values(array_diff(array_keys($activeBefore), array_keys(array_filter($rows, fn ($r) => $r['screened'] > 0))));

        $names = DB::table('facilities')->whereIn('facilityId', array_merge(array_keys($rows), $quietIds))
            ->get(['facilityId', 'facilityName', 'facilityState'])->keyBy('facilityId');
        $label = fn ($id) => ($names[$id]->facilityName ?? "Facility #{$id}") . (!empty($names[$id]->facilityState) ? ', ' . $names[$id]->facilityState : '');

        $facilityRows = collect($rows)->map(fn ($r) => $r + ['facilityName' => $label($r['facilityId'])])
            ->sortByDesc(fn ($r) => [$r['screened'], $r['samples']])->values()->all();

        // Things management should act on
        $attention = array_values(array_filter([
            $notReferred ? ['tone' => 'red', 'text' => "{$notReferred} client(s) with a suspicious finding this week have not been referred."] : null,
            $lab['overdue'] ? ['tone' => 'red', 'text' => "{$lab['overdue']} sample(s) / test(s) have waited more than " . OverdueResults::DEFAULT_DAYS . " days for a result."] : null,
            $referrals['waitingOver14'] ? ['tone' => 'amber', 'text' => "{$referrals['waitingOver14']} referral(s) have been pending for more than 14 days."] : null,
            $referrals['formOnly'] ? ['tone' => 'amber', 'text' => "{$referrals['formOnly']} client(s) are marked \"Referred\" on a screening form but no referral to a facility was recorded."] : null,
            $quietIds ? ['tone' => 'amber', 'text' => count($quietIds) . ' facilit' . (count($quietIds) === 1 ? 'y' : 'ies') . ' screened clients in the previous 4 weeks but none this week.'] : null,
        ]));

        return [
            'period' => [
                'from' => $f, 'to' => $t,
                'label' => $from->format('j M') . ' – ' . $to->format('j M Y'),
                'previousLabel' => Carbon::parse($pf)->format('j M') . ' – ' . Carbon::parse($pt)->format('j M'),
            ],
            'kpis' => $kpis,
            'byCancerType' => $byType,
            'lab' => $lab,
            'referrals' => $referrals,
            'suspiciousNotReferred' => $notReferred,
            'treatment' => $treatment,
            'facilities' => array_slice($facilityRows, 0, 20),
            'facilityCount' => count($facilityRows),
            'quietFacilities' => array_map($label, array_slice($quietIds, 0, 15)),
            'quietCount' => count($quietIds),
            'attention' => $attention,
        ];
    }
}
