<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Client-level screening findings, built with the SAME rules as the Screened Clients page, so the dashboard,
 * the analytics page and Screened Clients all give the same numbers.
 *
 * Per screening record (one cancer type):
 *   1. its own screeningResult (positive = suspicious, negative/normal = non-suspicious), else
 *   2. the HPV result on the form (pos / neg), else
 *   3. the visit's overall outcome (suspicious / urgent referral = suspicious, any other outcome = non-suspicious).
 * Lab tests: released urgent/abnormal = suspicious, normal = non-suspicious, other = inconclusive,
 *            collected with no result = awaiting.
 * One finding per client per cancer type; the most serious wins:
 *   suspicious > non_suspicious > inconclusive > awaiting > null (screened, nothing recorded).
 *
 * Facility scope: the screening visit's facility for forms (as on Screened Clients) and the sample's facility for lab tests.
 */
class ClientFindings
{
    public const MODULES = [
        'cervical'   => 'cervical_screenings',
        'breast'     => 'breast_screenings',
        'prostate'   => 'prostate_screenings',
        'colorectal' => 'colorectal_screenings',
        'liver'      => 'liver_screenings',
    ];

    public const SUSPICIOUS_OUTCOMES = ['suspicious', 'urgent_referral'];
    public const SUSPICIOUS_SEVERITIES = ['urgent', 'abnormal'];

    private const RANK = ['awaiting' => 1, 'inconclusive' => 2, 'non_suspicious' => 3, 'suspicious' => 4];
    private const OUTCOME_RANK = ['normal' => 1, 'low_suspicion' => 2, 'suspicious' => 3, 'urgent_referral' => 4];

    /** Stored form value -> finding. */
    public static function normalise(?string $v): ?string
    {
        $v = strtolower(trim((string) $v));
        if ($v === '') return null;
        if (in_array($v, ['suspicious', 'positive', 'abnormal'], true)) return 'suspicious';
        if (in_array($v, ['non_suspicious', 'non-suspicious', 'negative', 'normal'], true)) return 'non_suspicious';
        if ($v === 'inconclusive') return 'inconclusive';
        return null;
    }

    /** Lab severity -> finding. */
    public static function fromSeverity(?string $severity): string
    {
        $s = strtolower((string) $severity);
        if (in_array($s, self::SUSPICIOUS_SEVERITIES, true)) return 'suspicious';
        return $s === 'normal' ? 'non_suspicious' : 'inconclusive';
    }

    private static function better(?string $current, ?string $new): ?string
    {
        if ($new === null) return $current;
        if ($current === null) return $new;
        return self::RANK[$new] > self::RANK[$current] ? $new : $current;
    }

    /**
     * Every screened client in scope.
     * Returns [clientId => [
     *   'types'      => [cancerType => finding|null],
     *   'outcome'    => most serious overall outcome on any visit (normal|low_suspicion|suspicious|urgent_referral|null),
     *   'facilityId' => facility of the latest screening visit / sample,
     *   'firstDate'  => first screening or sample date,
     * ]]
     *
     * @param array|null $facilities null = every facility
     */
    public static function build(?array $facilities, ?string $from = null, ?string $to = null): array
    {
        $clients = [];
        $touch = function (string $clientId, $facilityId, ?string $date) use (&$clients) {
            $c = &$clients[$clientId];
            $c ??= ['types' => [], 'outcome' => null, 'facilityId' => null, 'firstDate' => null, 'lastDate' => null];
            $date = $date ? substr($date, 0, 10) : null;
            if ($date && ($c['firstDate'] === null || $date < $c['firstDate'])) $c['firstDate'] = $date;
            if ($date && ($c['lastDate'] === null || $date >= $c['lastDate'])) {
                $c['lastDate'] = $date;
                if ($facilityId) $c['facilityId'] = $facilityId;
            }
            if (!$c['facilityId'] && $facilityId) $c['facilityId'] = $facilityId;
            unset($c);
        };

        // ---- Screening forms ----
        foreach (self::MODULES as $type => $table) {
            if (!Schema::hasTable($table)) continue;
            $hasHpv = Schema::hasColumn($table, 'hpvResult');
            $hasResult = Schema::hasColumn($table, 'screeningResult');
            $hasDate = Schema::hasColumn($table, 'screeningDate');
            $date = $hasDate ? "COALESCE({$table}.screeningDate, screening_visits.visitDate)" : 'screening_visits.visitDate';

            $rows = DB::table($table)
                ->join('screening_visits', "{$table}.visitId", '=', 'screening_visits.visitId')
                ->when($facilities !== null, fn ($q) => $q->whereIn('screening_visits.facilityId', $facilities))
                ->when($from, fn ($q) => $q->whereDate(DB::raw($date), '>=', $from))
                ->when($to, fn ($q) => $q->whereDate(DB::raw($date), '<=', $to))
                ->selectRaw(implode(', ', array_filter([
                    'screening_visits.clientId as clientId',
                    'screening_visits.facilityId as facilityId',
                    'screening_visits.overallOutcome as overallOutcome',
                    "DATE({$date}) as d",
                    $hasResult ? "{$table}.screeningResult as result" : 'NULL as result',
                    $hasHpv ? "{$table}.hpvResult as hpv" : 'NULL as hpv',
                ])))
                ->get();

            foreach ($rows as $r) {
                if (!$r->clientId) continue;
                $touch($r->clientId, $r->facilityId, $r->d);

                $finding = self::normalise($r->result);
                if ($finding === null && $r->hpv) {
                    $hpv = strtolower((string) $r->hpv);
                    $finding = str_contains($hpv, 'pos') ? 'suspicious' : (str_contains($hpv, 'neg') ? 'non_suspicious' : null);
                }
                if ($finding === null && $r->overallOutcome) {
                    $finding = in_array($r->overallOutcome, self::SUSPICIOUS_OUTCOMES, true) ? 'suspicious' : 'non_suspicious';
                }
                $clients[$r->clientId]['types'][$type] = self::better($clients[$r->clientId]['types'][$type] ?? null, $finding);
            }
        }

        // ---- Overall outcome of every visit (also visits without a module record) ----
        $visits = DB::table('screening_visits')
            ->whereNotNull('overallOutcome')->where('overallOutcome', '!=', '')
            ->when($facilities !== null, fn ($q) => $q->whereIn('facilityId', $facilities))
            ->when($from, fn ($q) => $q->whereDate('visitDate', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('visitDate', '<=', $to))
            ->select('clientId', 'facilityId', 'overallOutcome', DB::raw('DATE(visitDate) as d'))
            ->get();
        foreach ($visits as $v) {
            if (!$v->clientId) continue;
            $touch($v->clientId, $v->facilityId, $v->d);
            $cur = $clients[$v->clientId]['outcome'];
            if (isset(self::OUTCOME_RANK[$v->overallOutcome])
                && ($cur === null || self::OUTCOME_RANK[$v->overallOutcome] > self::OUTCOME_RANK[$cur])) {
                $clients[$v->clientId]['outcome'] = $v->overallOutcome;
            }
        }

        // ---- Lab tests ----
        $hasDeletedAt = Schema::hasColumn('screening_samples', 'deletedAt');
        $samples = DB::table('screening_samples')
            ->when($hasDeletedAt, fn ($q) => $q->whereNull('deletedAt'))
            ->whereIn('status', ['collected', 'result_released'])
            ->when($facilities !== null, fn ($q) => $q->whereIn('facilityId', $facilities))
            ->when($from, fn ($q) => $q->whereDate('sampleDate', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('sampleDate', '<=', $to))
            ->select('clientId', 'facilityId', 'cancerType', 'status', 'resultSeverity', DB::raw('DATE(sampleDate) as d'))
            ->get();
        foreach ($samples as $s) {
            if (!$s->clientId || !$s->cancerType) continue;
            $touch($s->clientId, $s->facilityId, $s->d);
            $finding = $s->status === 'result_released' ? self::fromSeverity($s->resultSeverity) : 'awaiting';
            $clients[$s->clientId]['types'][$s->cancerType] = self::better($clients[$s->clientId]['types'][$s->cancerType] ?? null, $finding);
        }

        return $clients;
    }

    /** Is the client suspicious for any cancer type (or by the visit outcome)? */
    public static function isSuspicious(array $c): bool
    {
        return in_array('suspicious', $c['types'], true) || in_array($c['outcome'], self::SUSPICIOUS_OUTCOMES, true);
    }

    /** Client ids screened (a form or lab test) — visits with only an outcome and no screening record are left out. */
    public static function screenedIds(array $clients, ?string $cancerType = null): array
    {
        $ids = [];
        foreach ($clients as $id => $c) {
            if ($cancerType ? array_key_exists($cancerType, $c['types']) : !empty($c['types'])) $ids[] = $id;
        }
        return $ids;
    }
}
