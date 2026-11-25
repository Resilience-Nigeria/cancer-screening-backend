<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClientReferral;
use App\Support\Referrals;
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
     * Referrals sent or received by the user's facilities (all facilities for national roles).
     * ?status=active|pending|accepted|completed|declined|form_only &type= &direction=sent|received
     * &search= &dateFrom= &dateTo= &excludeSameFacility=1 &page= &perPage=
     *
     * Same-facility referrals (the facility is Stage 3-capable, so the client continues there) ARE listed:
     * they still need the Stage 3 work and they are counted on the dashboard. Pass excludeSameFacility=1 to hide them.
     *
     * status=form_only lists clients marked "Referred" on a screening form who have no referral record yet.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        $visible = $user->visibleFacilityIds();   // null = every facility
        $search = trim((string) $request->query('search', ''));
        $direction = (string) $request->query('direction', '');
        $status = (string) $request->query('status', '');
        $from = $request->query('dateFrom') ?: null;
        $to = $request->query('dateTo') ?: null;
        $perPage = max(1, min((int) $request->query('perPage', 20), 100));
        $facilityFilter = $request->filled('facilityId') && $request->facilityId !== 'all' ? (int) $request->facilityId : null;
        if ($facilityFilter && $visible !== null && !in_array($facilityFilter, array_map('intval', $visible), true)) {
            $facilityFilter = null; // not one of theirs: ignore
        }

        // "Sent" side: the sending facility, or the client's own facility for older rows without one.
        $sentBy = function ($q) use ($visible) {
            $q->where(function ($w) use ($visible) {
                $w->whereIn('fromFacilityId', $visible)
                  ->orWhere(fn ($x) => $x->whereNull('fromFacilityId')->whereHas('client', fn ($c) => $c->whereIn('facilityId', $visible)));
            });
        };

        $base = ClientReferral::query()
            ->whereNotNull('toFacilityId')
            ->whereHas('client')
            ->when($request->boolean('excludeSameFacility'), fn ($q) => $q->where(
                fn ($w) => $w->whereNull('fromFacilityId')->orWhereColumn('fromFacilityId', '!=', 'toFacilityId')
            ))
            ->when($visible !== null, function ($q) use ($visible, $direction, $sentBy) {
                if ($direction === 'sent') {
                    $sentBy($q);
                } elseif ($direction === 'received') {
                    $q->whereIn('toFacilityId', $visible);
                } else {
                    $q->where(fn ($w) => $w->whereIn('toFacilityId', $visible)->orWhere(fn ($x) => $sentBy($x)));
                }
            })
            ->when($request->query('type'), fn ($q, $v) => $q->where('referralType', $v))
            // One hospital (national users, or one of the user's own facilities): referrals sent from or to it.
            ->when($facilityFilter, fn ($q) => $q->where(fn ($w) => $w->where('toFacilityId', $facilityFilter)->orWhere('fromFacilityId', $facilityFilter)))
            ->when($from, fn ($q, $v) => $q->whereDate('referralDate', '>=', $v))
            ->when($to, fn ($q, $v) => $q->whereDate('referralDate', '<=', $v))
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
        $clients = (clone $base)->distinct()->count('clientId');
        $sameFacility = (clone $base)->whereColumn('fromFacilityId', 'toFacilityId')->count();

        // Marked "Referred" on the screening form, no referral record yet. These belong to the sending side.
        $formOnly = $direction === 'received' || $request->query('type')
            ? collect()
            : Referrals::formOnly($facilityFilter ? [$facilityFilter] : $visible, $from, $to, $search);

        $currentPage = max(1, (int) $request->query('page', 1));

        if ($status === 'form_only') {
            $slice = $formOnly->slice(($currentPage - 1) * $perPage, $perPage)->values();
            $facilities = \App\Models\Facility::whereIn('facilityId', $slice->pluck('facilityId')->filter()->unique())->get()->keyBy('facilityId');
            $rows = $slice->map(fn ($r) => [
                'referralId'   => null,
                'key'          => 'form-' . $r->clientId,
                'clientId'     => $r->clientId,
                'status'       => 'form_only',
                'referralType' => null,
                'referralDate' => $r->screeningDate,
                'notes'        => 'Marked "Referred" on the ' . implode(', ', $r->cancerTypes) . ' screening form. No referral to a facility has been recorded yet.',
                'cancerTypes'  => $r->cancerTypes,
                'client'       => ['clientId' => $r->clientId, 'fullName' => $r->fullName, 'phoneNumber' => $r->phoneNumber, 'email' => $r->email],
                'fromFacility' => $this->facility($facilities->get($r->facilityId)),
                'toFacility'   => null,
                'sameFacility' => false,
                'formOnly'     => true,
                'canUpdate'    => false,
            ])->values();
            $total = $formOnly->count();
        } else {
            $page = (clone $base)
                ->when($status !== '', function ($q) use ($status) {
                    // "active" = still open (the old Linked Clients inbox): pending + accepted
                    $status === 'active' ? $q->whereIn('status', ['pending', 'accepted']) : $q->where('status', $status);
                })
                ->with([
                    'client:clientId,fullName,gender,dateOfBirth,phoneNumber,email,hospitalNumber,facilityId',
                    'fromFacility',
                    'toFacility',
                ])
                ->orderByDesc('referralDate')
                ->orderByDesc('referralId')
                ->paginate($perPage, ['*'], 'page', $currentPage);

            // Laravel snake_cases relation names when serialising (from_facility), so build the rows explicitly.
            $rows = collect($page->items())->map(function (ClientReferral $r) use ($visible) {
                return array_merge($r->attributesToArray(), [
                    'key'          => 'ref-' . $r->referralId,
                    'client'       => $r->client,
                    'fromFacility' => $this->facility($r->fromFacility),
                    'toFacility'   => $this->facility($r->toFacility),
                    'sameFacility' => $r->fromFacilityId !== null && (int) $r->fromFacilityId === (int) $r->toFacilityId,
                    'formOnly'     => false,
                    // Only the receiving side acts on a referral.
                    'canUpdate'    => $visible === null || in_array($r->toFacilityId, $visible),
                ]);
            })->values();
            $total = $page->total();
        }

        return response()->json([
            'referrals' => $rows,
            'total'     => $total,
            'page'      => $currentPage,
            'perPage'   => $perPage,
            'types'     => $types,
            'summary'   => [
                'total'        => (int) $byStatus->sum(),
                'clients'      => (int) $clients,
                'active'       => (int) (($byStatus['pending'] ?? 0) + ($byStatus['accepted'] ?? 0)),
                'pending'      => (int) ($byStatus['pending'] ?? 0),
                'accepted'     => (int) ($byStatus['accepted'] ?? 0),
                'completed'    => (int) ($byStatus['completed'] ?? 0),
                'declined'     => (int) ($byStatus['declined'] ?? 0),
                'sameFacility' => (int) $sameFacility,
                'formOnly'     => $formOnly->count(),
                'byStatus'     => $byStatus,
            ],
        ]);
    }
}
