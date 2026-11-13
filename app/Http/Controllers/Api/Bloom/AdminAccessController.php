<?php

namespace App\Http\Controllers\Api\Bloom;

use App\Http\Controllers\Controller;
use App\Models\BloomAccessRequest;
use App\Models\BloomCase;
use App\Models\User;
use App\Services\Bloom\BloomNcsrClientService;
use App\Services\Bloom\BloomStaffNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Admins decide health workers' requests to attend to a client. Approving opens a Bloom case for her
 * (or moves her open case) and assigns it to the health worker. Super admin, NICRAT admin and
 * hospital admins; facility-scoped.
 */
class AdminAccessController extends Controller
{
    use CaseConcerns;

    /** GET /bloom-admin/access-requests?status=pending|approved|rejected|all&search= */
    public function index(Request $request): JsonResponse
    {
        $me = $this->staff();
        if (!$this->canReview($me)) {
            return $this->deny('Only admins can review access requests.');
        }

        $status = (string) $request->query('status', 'pending');
        $search = trim((string) $request->query('search', ''));

        $base = $this->scopeFacilities(BloomAccessRequest::query(), $me);
        $counts = (clone $base)->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        $rows = $base->with(['user', 'client', 'facility'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('client_id', 'like', "%{$search}%")
                      ->orWhereHas('client', fn ($c) => $c->where('fullName', 'like', "%{$search}%"))
                      ->orWhereHas('user', fn ($u) => $u->where('firstName', 'like', "%{$search}%")->orWhere('lastName', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('id')->limit(300)->get();

        $openCases = BloomCase::with('navigator')->whereIn('ncsr_client_id', $rows->pluck('client_id')->unique()->values())
            ->whereNull('closed_at')->get()->keyBy('ncsr_client_id');

        $countsOut = [
            'pending'  => (int) ($counts['pending'] ?? 0),
            'approved' => (int) ($counts['approved'] ?? 0),
            'rejected' => (int) ($counts['rejected'] ?? 0),
        ];

        return response()->json([
            'status'  => true,
            'counts'  => $countsOut,
            'summary' => $countsOut, // same numbers, read by the Bloom dashboard
            'data'    => $rows->map(function (BloomAccessRequest $r) use ($openCases) {
                $u = $r->user;
                $c = $r->client;
                $case = $openCases[$r->client_id] ?? null;

                return [
                    'id'           => $r->id,
                    'status'       => $r->status,
                    'reason'       => $r->reason,
                    'decisionNote' => $r->decision_note,
                    'createdAt'    => optional($r->created_at)->toIso8601String(),
                    'decidedAt'    => optional($r->decided_at)->toIso8601String(),
                    'caseId'       => $r->case_id,
                    'healthWorker' => $u ? ['id' => $u->id, 'name' => trim("{$u->firstName} {$u->lastName}"), 'phone' => $u->phoneNumber, 'email' => $u->email] : null,
                    'client'       => $c ? ['clientId' => $c->clientId, 'name' => $c->fullName, 'age' => self::clientAge($c), 'phone' => $c->phoneNumber, 'lga' => $c->lgaOfResidence ?? null] : ['clientId' => $r->client_id],
                    'facility'     => $r->facility ? ['id' => (int) $r->facility->facilityId, 'name' => $r->facility->facilityName] : null,
                    // Who is following her now, so the admin knows approving will move the case.
                    'currentCase'  => $case ? ['id' => $case->id, 'code' => $case->code(), 'navigator' => $case->navigator ? trim("{$case->navigator->firstName} {$case->navigator->lastName}") : null] : null,
                ];
            })->values(),
        ]);
    }

    /** POST /bloom-admin/access-requests/{id}/approve  { note? } */
    public function approve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:300']]);
        [$row, $error] = $this->load($id);
        if ($error) {
            return $error;
        }

        $hw = User::find($row->user_id);
        if (!$this->isActiveNavigator($hw)) {
            return response()->json(['status' => false, 'code' => 'NOT_ACTIVE', 'message' => 'This health worker is no longer active. Reject the request instead.'], 422);
        }
        if (!$row->client) {
            return response()->json(['status' => false, 'code' => 'CLIENT_MISSING', 'message' => 'The client record no longer exists. Reject the request instead.'], 422);
        }

        $me = $this->staff();
        $note = isset($data['note']) ? trim($data['note']) ?: null : null;

        [$case, $previous] = DB::transaction(function () use ($row, $hw, $me, $note) {
            $case = BloomCase::where('ncsr_client_id', $row->client_id)->whereNull('closed_at')->lockForUpdate()->orderByDesc('id')->first();
            $previous = $case?->navigator_id;

            if ($case) {
                $case->update(['navigator_id' => $hw->id, 'assigned_by' => $me->id, 'assigned_at' => now()]);
            } else {
                $client = $row->client;
                // Link her Bloom account if she has one, so her name/phone come from there too.
                $accountId = null;
                $variants = BloomNcsrClientService::phoneVariants($client->phoneNumber);
                if ($variants) {
                    $accountId = \App\Models\BloomAccount::whereIn('phone', $variants)->value('id');
                }

                $case = BloomCase::create([
                    'appointment_id' => null,
                    'account_id'     => $accountId,
                    'ncsr_client_id' => $client->clientId,
                    'facility_id'    => $row->facility_id,
                    'navigator_id'   => $hw->id,
                    'assigned_by'    => $me->id,
                    'assigned_at'    => now(),
                    'step_community' => 'not-started',
                    'step_phc'       => 'not-started',
                    'step_referral'  => 'not-started',
                    'step_followup'  => 'not-started',
                ]);
            }

            $row->update(['status' => 'approved', 'decision_note' => $note, 'decided_by' => $me->id, 'decided_at' => now(), 'case_id' => $case->id]);

            // Other health workers waiting on the same woman are told it went to someone else.
            BloomAccessRequest::where('client_id', $row->client_id)->where('status', 'pending')->where('id', '!=', $row->id)->get()
                ->each(function ($other) use ($me, $row) {
                    $other->update(['status' => 'rejected', 'decision_note' => 'Another health worker was approved to attend to her.', 'decided_by' => $me->id, 'decided_at' => now()]);
                    BloomStaffNotifier::notify($other->user_id, 'access_rejected', 'Access request not approved', ($row->client->fullName ?? $row->client_id) . ' was assigned to another health worker.');
                });

            return [$case, $previous];
        });

        $name = $row->client->fullName ?? $row->client_id;
        BloomStaffNotifier::notify($hw->id, 'access_approved', 'Access approved: ' . $name, trim('You can now attend to her (' . $case->code() . '). ' . ($note ?? '')), $case->id);
        if ($previous && (int) $previous !== (int) $hw->id) {
            BloomStaffNotifier::notify((int) $previous, 'case_reassigned', 'Case moved: ' . $case->code(), 'An admin gave this case to another health worker.', $case->id);
        }

        return response()->json(['status' => true, 'message' => 'Approved. The case is now assigned to the health worker.', 'data' => ['caseId' => $case->id, 'code' => $case->code()]]);
    }

    /** POST /bloom-admin/access-requests/{id}/reject  { note } */
    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:3', 'max:300']]);
        [$row, $error] = $this->load($id);
        if ($error) {
            return $error;
        }

        $row->update(['status' => 'rejected', 'decision_note' => trim($data['note']), 'decided_by' => $this->staff()->id, 'decided_at' => now()]);
        BloomStaffNotifier::notify($row->user_id, 'access_rejected', 'Access request not approved', ($row->client->fullName ?? $row->client_id) . ': ' . trim($data['note']));

        return response()->json(['status' => true, 'message' => 'Request rejected.']);
    }

    /** @return array{0: ?BloomAccessRequest, 1: ?JsonResponse} */
    private function load(int $id): array
    {
        $me = $this->staff();
        if (!$this->canReview($me)) {
            return [null, $this->deny('Only admins can review access requests.')];
        }
        $row = BloomAccessRequest::with('client')->find($id);
        if (!$row || !$me->canAccessFacility((int) $row->facility_id)) {
            return [null, response()->json(['status' => false, 'code' => 'NOT_FOUND', 'message' => 'Request not found.'], 404)];
        }
        if ($row->status !== 'pending') {
            return [null, response()->json(['status' => false, 'code' => 'ALREADY_DECIDED', 'message' => 'This request was already ' . $row->status . '.'], 422)];
        }

        return [$row, null];
    }
}
