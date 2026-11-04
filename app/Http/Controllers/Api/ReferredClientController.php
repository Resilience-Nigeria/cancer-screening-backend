<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientReferral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferredClientController extends Controller
{
    private function facility($f): ?array
    {
        return $f ? [
            'facilityId'    => $f->facilityId,
            'facilityName'  => $f->facilityName,
            'facilityState' => $f->facilityState ?? null,
            // Shown to the sending team so they know who to call at the receiving facility.
            'navigatorName'  => $f->navigatorName ?? null,
            'navigatorPhone' => $f->navigatorPhone ?? null,
            'navigatorEmail' => $f->navigatorEmail ?? null,
        ] : null;
    }

    /**
     * GET /referred-clients
     * Clients who were actually referred to another facility, sent or received by the user's facilities
     * (all facilities for national roles).
     * ?status=active|pending|accepted|completed|declined &type= &direction=sent|received &search= &dateFrom= &dateTo= &includeSameFacility=1 &page= &perPage=
     *
     * Referrals where the client simply continues at the same facility (from = to) are left out unless
     * includeSameFacility is set, because nobody was referred anywhere.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $visible = $user->visibleFacilityIds();   // null = every facility
        $search = trim((string) $request->query('search', ''));

        $base = ClientReferral::query()
            ->whereNotNull('toFacilityId')
            ->whereHas('client')
            ->when(!$request->boolean('includeSameFacility'), fn ($q) => $q->whereColumn('fromFacilityId', '!=', 'toFacilityId'))
            ->when($visible !== null, function ($q) use ($visible, $request) {
                $direction = $request->query('direction');
                if ($direction === 'sent') {
                    $q->whereIn('fromFacilityId', $visible);
                } elseif ($direction === 'received') {
                    $q->whereIn('toFacilityId', $visible);
                } else {
                    $q->where(fn ($w) => $w->whereIn('fromFacilityId', $visible)->orWhereIn('toFacilityId', $visible));
                }
            })
            ->when($request->query('type'), fn ($q, $v) => $q->where('referralType', $v))
            ->when($request->query('dateFrom'), fn ($q, $v) => $q->whereDate('referralDate', '>=', $v))
            ->when($request->query('dateTo'), fn ($q, $v) => $q->whereDate('referralDate', '<=', $v))
            ->when($search !== '', function ($q) use ($search) {
                $q->whereHas('client', function ($c) use ($search) {
                    $c->where('clientId', 'like', "%{$search}%")
                      ->orWhere('fullName', 'like', "%{$search}%")
                      ->orWhere('phoneNumber', 'like', "%{$search}%");
                });
            });

        // Totals ignore the status filter so the tiles stay clickable filters.
        $byStatus = (clone $base)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');
        $types = (clone $base)->whereNotNull('referralType')->distinct()->pluck('referralType')->values();

        $page = (clone $base)
            ->when($request->query('status'), function ($q, $v) {
                // "active" = still open (the old Linked Clients inbox): pending + accepted
                $v === 'active' ? $q->whereIn('status', ['pending', 'accepted']) : $q->where('status', $v);
            })
            ->with([
                'client:clientId,fullName,gender,dateOfBirth,phoneNumber,email,hospitalNumber',
                'fromFacility',
                'toFacility',
            ])
            ->orderByDesc('referralDate')
            ->orderByDesc('referralId')
            ->paginate(min((int) $request->query('perPage', 20), 100));

        // Laravel snake_cases relation names when serialising (from_facility), so build the rows explicitly.
        $rows = collect($page->items())->map(function (ClientReferral $r) use ($visible) {
            return array_merge($r->attributesToArray(), [
                'client'       => $r->client,
                'fromFacility' => $this->facility($r->fromFacility),
                'toFacility'   => $this->facility($r->toFacility),
                // Only the receiving side acts on a referral.
                'canUpdate'    => $visible === null || in_array($r->toFacilityId, $visible),
            ]);
        })->values();

        return response()->json([
            'referrals' => $rows,
            'total'     => $page->total(),
            'page'      => $page->currentPage(),
            'perPage'   => $page->perPage(),
            'types'     => $types,
            'summary'   => [
                'total'     => (int) $byStatus->sum(),
                'active'    => (int) (($byStatus['pending'] ?? 0) + ($byStatus['accepted'] ?? 0)),
                'pending'   => (int) ($byStatus['pending'] ?? 0),
                'accepted'  => (int) ($byStatus['accepted'] ?? 0),
                'completed' => (int) ($byStatus['completed'] ?? 0),
                'declined'  => (int) ($byStatus['declined'] ?? 0),
                'byStatus'  => $byStatus,
            ],
        ]);
    }
}
