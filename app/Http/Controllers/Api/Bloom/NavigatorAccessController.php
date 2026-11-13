<?php

namespace App\Http\Controllers\Api\Bloom;

use App\Http\Controllers\Controller;
use App\Models\BloomAccessRequest;
use App\Models\BloomCase;
use App\Models\BloomStaffNotification;
use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * For the health worker:
 *  - her notification bell
 *  - searching NCSR clients at her facility (registered there, or referred there) with minimal details
 *  - asking an admin for permission to attend to one of them
 */
class NavigatorAccessController extends Controller
{
    use CaseConcerns;

    /** GET /bloom-chw/notifications */
    public function notifications(): JsonResponse
    {
        $me = $this->staff();
        if (!$this->isActiveNavigator($me)) {
            return $this->deny('Only verified community health workers have notifications.');
        }

        $rows = BloomStaffNotification::where('user_id', $me->id)->orderByDesc('id')->limit(100)->get();

        return response()->json([
            'status' => true,
            'data'   => [
                'unread' => BloomStaffNotification::where('user_id', $me->id)->whereNull('read_at')->count(),
                'items'  => $rows->map(fn ($n) => [
                    'id'        => $n->id,
                    'type'      => $n->type,
                    'title'     => $n->title,
                    'body'      => $n->body,
                    'caseId'    => $n->case_id,
                    'read'      => $n->read_at !== null,
                    'createdAt' => optional($n->created_at)->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    /** POST /bloom-chw/notifications/read  { ids?: int[] } — no ids marks everything read. */
    public function markRead(Request $request): JsonResponse
    {
        $me = $this->staff();
        if (!$this->isActiveNavigator($me)) {
            return $this->deny();
        }
        $data = $request->validate(['ids' => ['nullable', 'array', 'max:200'], 'ids.*' => ['integer']]);

        BloomStaffNotification::where('user_id', $me->id)->whereNull('read_at')
            ->when(!empty($data['ids']), fn ($q) => $q->whereIn('id', $data['ids']))
            ->update(['read_at' => now()]);

        return response()->json(['status' => true]);
    }

    /** GET /bloom-chw/clients/search?q= — name, Client ID, phone or hospital number; at least 2 characters. */
    public function search(Request $request): JsonResponse
    {
        $me = $this->staff();
        if (!$this->isActiveNavigator($me)) {
            return $this->deny('Only verified community health workers can search clients.');
        }
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['status' => true, 'data' => []]);
        }

        $digits = preg_replace('/\D+/', '', $q);
        $list = $this->atMyFacility(Client::query(), $me)
            ->where(function ($w) use ($q, $digits) {
                $w->where('fullName', 'like', "%{$q}%")
                  ->orWhere('clientId', 'like', "%{$q}%");
                if (Schema::hasColumn('clients', 'hospitalNumber')) {
                    $w->orWhere('hospitalNumber', $q);
                }
                if (strlen($digits) >= 7) {
                    // 0803..., 234803... and +234803... all match on the last 10 digits.
                    $w->orWhere('phoneNumber', 'like', '%' . substr($digits, -10));
                }
            })
            ->orderBy('fullName')->limit(30)->get();

        $ids = $list->pluck('clientId')->all();

        $openCases = BloomCase::whereIn('ncsr_client_id', $ids ?: ['-'])->whereNull('closed_at')->get()->keyBy('ncsr_client_id');
        $myPending = BloomAccessRequest::where('user_id', $me->id)->where('status', 'pending')
            ->whereIn('client_id', $ids ?: ['-'])->pluck('id', 'client_id');

        return response()->json([
            'status' => true,
            'data'   => $list->map(function (Client $c) use ($me, $openCases, $myPending) {
                $case = $openCases[$c->clientId] ?? null;
                $access = match (true) {
                    $case && (int) $case->navigator_id === (int) $me->id => 'mine',
                    isset($myPending[$c->clientId])                       => 'pending',
                    $case && $case->navigator_id !== null                 => 'other',
                    default                                               => 'none',
                };

                return [
                    'clientId' => $c->clientId,
                    'name'     => $c->fullName,
                    'age'      => self::clientAge($c),
                    'lga'      => $c->lgaOfResidence ?? null,
                    // Only enough of the number to tell two women with the same name apart.
                    'phone'    => self::maskPhone($c->phoneNumber),
                    'access'   => $access,
                    'caseId'   => $access === 'mine' ? $case->id : null,
                ];
            })->values(),
        ]);
    }

    /** GET /bloom-chw/access-requests — her own requests, newest first. */
    public function index(): JsonResponse
    {
        $me = $this->staff();
        if (!$this->isActiveNavigator($me)) {
            return $this->deny();
        }

        $rows = BloomAccessRequest::with('client')->where('user_id', $me->id)->orderByDesc('id')->limit(100)->get();

        return response()->json(['status' => true, 'data' => $rows->map(fn ($r) => $this->present($r))->values()]);
    }

    /** POST /bloom-chw/access-requests  { clientId, reason } */
    public function store(Request $request): JsonResponse
    {
        $me = $this->staff();
        if (!$this->isActiveNavigator($me)) {
            return $this->deny('Only verified community health workers can request access.');
        }
        $data = $request->validate([
            'clientId' => ['required', 'string', 'max:40'],
            'reason'   => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $client = $this->atMyFacility(Client::query(), $me)->where('clientId', trim($data['clientId']))->first();
        if (!$client) {
            return response()->json(['status' => false, 'code' => 'NOT_FOUND', 'message' => 'Client not found at your facility.'], 404);
        }

        $mine = BloomCase::where('ncsr_client_id', $client->clientId)->whereNull('closed_at')->where('navigator_id', $me->id)->first();
        if ($mine) {
            return response()->json(['status' => false, 'code' => 'ALREADY_YOURS', 'message' => 'You are already attending to her.', 'data' => ['caseId' => $mine->id]], 409);
        }
        if (BloomAccessRequest::where('user_id', $me->id)->where('client_id', $client->clientId)->where('status', 'pending')->exists()) {
            return response()->json(['status' => false, 'code' => 'ALREADY_REQUESTED', 'message' => 'You already asked. An admin will review it.'], 409);
        }

        $row = BloomAccessRequest::create([
            'user_id'     => $me->id,
            'client_id'   => $client->clientId,
            'facility_id' => (int) $me->facilityId,
            'reason'      => trim($data['reason']),
            'status'      => 'pending',
        ]);
        $row->setRelation('client', $client);

        return response()->json(['status' => true, 'message' => 'Request sent. An admin will review it.', 'data' => $this->present($row)], 201);
    }

    /** Clients registered at her facility or referred to it, women only (Bloom is breast health). */
    private function atMyFacility(Builder $q, $me): Builder
    {
        $fid = (int) $me->facilityId;

        return $q->where(function ($w) use ($fid) {
            $w->where('facilityId', $fid);
            if (Schema::hasTable('client_referrals')) {
                $w->orWhereIn('clientId', DB::table('client_referrals')->where('toFacilityId', $fid)->select('clientId'));
            }
        })->where(fn ($w) => $w->whereNull('gender')->orWhereRaw('LOWER(gender) <> ?', ['male']));
    }

    private static function maskPhone(?string $p): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $p);
        if (strlen($d) < 7) {
            return null;
        }
        if (str_starts_with($d, '234')) {
            $d = '0' . substr($d, 3);
        }

        return substr($d, 0, 4) . '•••' . substr($d, -3);
    }

    private function present(BloomAccessRequest $r): array
    {
        return [
            'id'           => $r->id,
            'clientId'     => $r->client_id,
            'clientName'   => $r->client->fullName ?? null,
            'reason'       => $r->reason,
            'status'       => $r->status,
            'decisionNote' => $r->decision_note,
            'caseId'       => $r->status === 'approved' ? $r->case_id : null,
            'createdAt'    => optional($r->created_at)->toIso8601String(),
            'decidedAt'    => optional($r->decided_at)->toIso8601String(),
        ];
    }
}
