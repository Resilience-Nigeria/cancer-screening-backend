<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ClientReferral;
use App\Models\Facility;
use App\Models\FollowUpContact;
use App\Models\ResultCommunication;
use App\Models\ScreeningSample;
use App\Models\ScreeningVisit;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Follow-ups & recalls — clients the facility needs to bring back, in one list:
 *   recall    repeat / routine screening due (follow_up_schedules, from the Stage 2 outcome)
 *   result    a released result not yet given back to the client
 *   booked    a booked test whose date has come
 *   referral  a referral still pending / accepted 14+ days after it was made
 * Each item can be called / SMSed and gets a contact log (reached, no answer, rescheduled …).
 */
class FollowUpController extends Controller
{
    private const STALLED_REFERRAL_DAYS = 14;
    private const RESULT_WINDOW_DAYS = 180;

    private function scope(Request $request): ?array
    {
        $user = auth('api')->user();
        $visible = $user->visibleFacilityIds();
        $fid = $request->filled('facilityId') && $request->facilityId !== 'all' ? (int) $request->facilityId : null;
        if ($fid && ($visible === null || in_array($fid, array_map('intval', $visible), true))) return [$fid];
        return $visible;
    }

    /** GET /mobile/follow-ups?when=overdue|today|week|all&kind=&facilityId=&search= */
    public function index(Request $request): JsonResponse
    {
        $scope = $this->scope($request);
        $today = Carbon::today();
        $horizon = $today->copy()->addDays(30);
        $items = collect()
            ->merge($this->recalls($scope, $horizon))
            ->merge($this->results($scope))
            ->merge($this->booked($scope, $horizon))
            ->merge($this->referrals($scope));

        // Latest contact per item: snoozed until nextContactDate; "returned" / "declined" closes it.
        $contacts = FollowUpContact::query()
            ->whereIn('clientId', $items->pluck('clientId')->unique())
            ->orderBy('id')->get()
            ->groupBy(fn ($c) => "{$c->kind}:{$c->refId}")
            ->map(fn ($g) => $g->last());

        $items = $items->map(function ($i) use ($contacts) {
            $c = $contacts->get("{$i['kind']}:{$i['refId']}");
            $i['lastContact'] = $c ? [
                'outcome' => $c->outcome, 'method' => $c->method, 'note' => $c->note,
                'at' => $c->created_at?->toIso8601String(), 'nextContactDate' => $c->nextContactDate?->toDateString(),
            ] : null;
            if ($c && $c->nextContactDate && $c->nextContactDate->isAfter(Carbon::today())) $i['dueDate'] = $c->nextContactDate->toDateString();
            $i['closed'] = $c && in_array($c->outcome, ['returned', 'declined'], true);
            return $i;
        })->reject(fn ($i) => $i['closed'])->values();

        // Client details
        $clients = Client::with('facility')->whereIn('clientId', $items->pluck('clientId')->unique())->get()->keyBy('clientId');
        $items = $items->map(function ($i) use ($clients, $today) {
            $c = $clients->get($i['clientId']);
            $due = Carbon::parse($i['dueDate']);
            return $i + [
                'clientName' => $c->fullName ?? $i['clientId'],
                'phone' => $c->phoneNumber ?? null,
                'caregiverPhone' => $c->caregiverPhoneNumber ?? null,
                'clientFacility' => $c?->facility?->facilityName,
                'daysOverdue' => $due->isBefore($today) ? $due->diffInDays($today) : 0,
                'status' => $due->isBefore($today) ? 'overdue' : ($due->isSameDay($today) ? 'today' : 'upcoming'),
            ];
        });

        // Counts (before the "when" / "kind" filters, so the chips show totals)
        $counts = [
            'overdue' => $items->where('status', 'overdue')->count(),
            'today' => $items->where('status', 'today')->count(),
            'week' => $items->filter(fn ($i) => Carbon::parse($i['dueDate'])->lte($today->copy()->addDays(7)))->count(),
            'byKind' => $items->groupBy('kind')->map->count(),
        ];

        $when = (string) $request->query('when', 'overdue');
        $kind = (string) $request->query('kind', '');
        $search = mb_strtolower(trim((string) $request->query('search', '')));
        $rows = $items
            ->when($kind !== '', fn ($c) => $c->where('kind', $kind))
            ->filter(fn ($i) => match ($when) {
                'overdue' => $i['status'] === 'overdue',
                'today' => in_array($i['status'], ['overdue', 'today'], true),
                'week' => Carbon::parse($i['dueDate'])->lte($today->copy()->addDays(7)),
                default => true,
            })
            ->filter(fn ($i) => $search === '' || str_contains(mb_strtolower("{$i['clientName']} {$i['clientId']} {$i['phone']}"), $search))
            ->sortBy([['dueDate', 'asc']])
            ->take(300)->values();

        return response()->json(['items' => $rows, 'counts' => $counts]);
    }

    private function recalls(?array $scope, Carbon $horizon): Collection
    {
        if (!class_exists(\App\Models\FollowUpSchedule::class) || !Schema::hasTable('follow_up_schedules')) return collect();
        $rows = \App\Models\FollowUpSchedule::query()
            ->where('status', 'pending')->whereNotNull('dueDate')->whereDate('dueDate', '<=', $horizon)
            ->when($scope !== null, fn ($q) => $q->whereIn('clientId', Client::whereIn('facilityId', $scope)->select('clientId')))
            ->orderBy('dueDate')->limit(1000)->get();

        // Already came back: a visit on/after (due date − 30 days) made after the schedule.
        $returned = ScreeningVisit::whereIn('clientId', $rows->pluck('clientId')->unique())
            ->get(['clientId', 'visitDate', 'created_at'])->groupBy('clientId');

        return $rows->reject(function ($s) use ($returned) {
            $from = Carbon::parse($s->dueDate)->subDays(30);
            return ($returned->get($s->clientId) ?? collect())->contains(fn ($v) => $v->visitDate && Carbon::parse($v->visitDate)->gte($from) && $v->created_at && $s->created_at && $v->created_at->gt($s->created_at));
        })->map(fn ($s) => [
            'id' => 'recall:' . $s->getKey(), 'kind' => 'recall', 'refId' => (string) $s->getKey(), 'clientId' => $s->clientId,
            'dueDate' => Carbon::parse($s->dueDate)->toDateString(),
            'title' => ($s->reason ?? '') === 'low_suspicion' ? 'Repeat screening (low suspicion)' : 'Routine re-screening due',
            'detail' => $s->activities ?? null,
        ])->values();
    }

    private function results(?array $scope): Collection
    {
        $given = ResultCommunication::query()->pluck('sampleId')->all();
        return ScreeningSample::query()
            ->where('status', 'result_released')
            ->whereDate('resultDate', '>=', now()->subDays(self::RESULT_WINDOW_DAYS))
            ->when($scope !== null, fn ($q) => $q->whereIn('facilityId', $scope))
            ->when($given, fn ($q) => $q->whereNotIn('sampleId', $given))
            ->orderBy('resultDate')->limit(1000)->get()
            ->map(fn ($s) => [
                'id' => 'result:' . $s->sampleId, 'kind' => 'result', 'refId' => (string) $s->sampleId, 'clientId' => $s->clientId,
                'dueDate' => Carbon::parse($s->resultDate ?? $s->updated_at)->toDateString(),
                'title' => 'Give result back',
                'detail' => (\App\Support\ScreeningTestCatalog::find($s->testType)['label'] ?? $s->testType) . ($s->labSampleNumber ? " · {$s->labSampleNumber}" : ''),
                'sampleId' => $s->sampleId,
            ])->values();
    }

    private function booked(?array $scope, Carbon $horizon): Collection
    {
        return ScreeningSample::query()
            ->where('status', 'booked')->whereDate('sampleDate', '<=', $horizon)
            ->when($scope !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('facilityId', $scope)->orWhereIn('bookedFacilityId', $scope)))
            ->orderBy('sampleDate')->limit(1000)->get()
            ->map(fn ($s) => [
                'id' => 'booked:' . $s->sampleId, 'kind' => 'booked', 'refId' => (string) $s->sampleId, 'clientId' => $s->clientId,
                'dueDate' => Carbon::parse($s->sampleDate)->toDateString(),
                'title' => 'Booked test',
                'detail' => (\App\Support\ScreeningTestCatalog::find($s->testType)['label'] ?? $s->testType)
                    . (($f = Facility::find($s->bookedFacilityId ?? $s->facilityId)) ? " at {$f->facilityName}" : ''),
                'sampleId' => $s->sampleId,
            ])->values();
    }

    private function referrals(?array $scope): Collection
    {
        $cut = now()->subDays(self::STALLED_REFERRAL_DAYS)->toDateString();
        return ClientReferral::query()
            ->whereIn('status', ['pending', 'accepted'])->whereDate('referralDate', '<=', $cut)
            ->when($scope !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('fromFacilityId', $scope)->orWhereIn('toFacilityId', $scope)))
            ->orderBy('referralDate')->limit(1000)->get()
            ->map(fn ($r) => [
                'id' => 'referral:' . $r->getKey(), 'kind' => 'referral', 'refId' => (string) $r->getKey(), 'clientId' => $r->clientId,
                'dueDate' => Carbon::parse($r->referralDate)->addDays(self::STALLED_REFERRAL_DAYS)->toDateString(),
                'title' => $r->status === 'accepted' ? 'Referral accepted, client not seen yet' : 'Referral not yet attended',
                'detail' => 'Referred ' . Carbon::parse($r->referralDate)->format('j M Y') . (($f = Facility::find($r->toFacilityId)) ? " to {$f->facilityName}" : ''),
            ])->values();
    }

    /** POST /mobile/follow-ups/contact { clientId, kind, refId, method, outcome, note?, nextContactDate? } */
    public function contact(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clientId' => ['required', 'string', 'max:60'],
            'kind' => ['required', 'in:recall,result,booked,referral'],
            'refId' => ['nullable', 'string', 'max:60'],
            'method' => ['nullable', 'in:call,sms,visit,whatsapp'],
            'outcome' => ['required', 'in:reached,no_answer,wrong_number,rescheduled,declined,returned'],
            'note' => ['nullable', 'string', 'max:1000'],
            'nextContactDate' => ['nullable', 'date'],
        ]);
        $client = Client::findOrFail($data['clientId']);
        abort_if(!auth('api')->user()->canAccessFacility($client->facilityId) && $data['kind'] !== 'referral', 403, 'You cannot access this client.');

        $row = FollowUpContact::create($data + ['userId' => auth('api')->id()]);

        // Client came back / declined: close the recall schedule too.
        if ($data['kind'] === 'recall' && in_array($data['outcome'], ['returned', 'declined'], true) && class_exists(\App\Models\FollowUpSchedule::class)) {
            \App\Models\FollowUpSchedule::whereKey($data['refId'])->update(['status' => 'completed']);
        }
        return response()->json(['message' => 'Saved.', 'contact' => $row], 201);
    }

    /** GET /mobile/follow-ups/history?clientId= — contacts and SMS for one client. */
    public function history(Request $request): JsonResponse
    {
        $cid = (string) $request->query('clientId');
        return response()->json([
            'contacts' => FollowUpContact::where('clientId', $cid)->orderByDesc('id')->limit(50)->get(),
            'sms' => \App\Models\SmsLog::where('clientId', $cid)->orderByDesc('id')->limit(50)->get(['id', 'purpose', 'message', 'status', 'created_at']),
        ]);
    }

    /** POST /mobile/sms { clientId, purpose: result_ready|recall|booked_test|referral|custom, message?, date? } */
    public function sms(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clientId' => ['required', 'string', 'max:60'],
            'purpose' => ['required', 'in:result_ready,recall,booked_test,referral,custom'],
            'message' => ['nullable', 'string', 'max:300'],
            'date' => ['nullable', 'string', 'max:40'],
            'refId' => ['nullable', 'string', 'max:60'],
            'kind' => ['nullable', 'in:recall,result,booked,referral'],
        ]);
        $client = Client::with('facility')->findOrFail($data['clientId']);
        abort_if($data['purpose'] === 'custom' && empty($data['message']), 422, 'Write the message.');

        $recent = \App\Models\SmsLog::where('clientId', $client->clientId)->where('purpose', $data['purpose'])
            ->where('created_at', '>=', now()->subHours(12))->whereIn('status', ['sent', 'logged'])->exists();
        abort_if($recent && $data['purpose'] !== 'custom', 429, 'This SMS was already sent to the client in the last 12 hours.');

        $fac = auth('api')->user()->facility->facilityName ?? $client->facility->facilityName ?? null;
        $text = SmsService::template($data['purpose'], ['name' => $client->fullName, 'facility' => $fac, 'date' => $data['date'] ?? null, 'message' => $data['message'] ?? null]);
        $res = SmsService::send($client->phoneNumber ?: ($client->caregiverPhoneNumber ?? null), $text, $data['purpose'], $client->clientId);

        if (!empty($data['kind'])) {
            FollowUpContact::create([
                'clientId' => $client->clientId, 'kind' => $data['kind'], 'refId' => $data['refId'] ?? null, 'method' => 'sms',
                'outcome' => 'rescheduled', 'note' => 'SMS: ' . $text, 'userId' => auth('api')->id(),
                'nextContactDate' => now()->addDays(3)->toDateString(),
            ]);
        }
        return response()->json($res + ['text' => $text], $res['ok'] ? 200 : 422);
    }
}
