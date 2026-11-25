<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Facility;
use App\Models\Role;
use App\Models\StaffBroadcast;
use App\Models\StaffNotification;
use App\Models\StaffPushToken;
use App\Models\User;
use App\Services\StaffPushService;
use App\Support\StaffAudience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Web dashboard → staff phones. Super admins and NICRAT admins send a message to everyone, chosen roles,
 * chosen facilities (or both), or named people. It lands in the app's bell and, if asked, as a phone notification.
 */
class StaffBroadcastController extends Controller
{
    /** Screens the app can open when the notification is tapped. */
    private const ROUTES = ['/notifications', '/', '/clients', '/tests', '/referrals', '/sync', '/profile', '/collect-sample', '/deletion-requests', '/follow-ups', '/stock', '/chats', '/help'];

    private function admin(): User
    {
        $u = auth('api')->user();
        abort_if(!$u || !($u->isSuperAdmin() || $u->isNicratStaff()), 403, 'Only a super admin or NICRAT admin can send staff notifications.');
        return $u;
    }

    private function audienceFrom(Request $request): array
    {
        $a = (array) $request->input('audience', []);
        return [
            'roles'       => array_values(array_filter((array) ($a['roles'] ?? []), 'is_string')),
            'facilityIds' => array_values(array_filter(array_map('intval', (array) ($a['facilityIds'] ?? [])))),
            'userIds'     => array_values(array_filter(array_map('intval', (array) ($a['userIds'] ?? [])))),
        ];
    }

    /** GET /staff-notifications/options — roles, facilities and staff to choose from. */
    public function options(): JsonResponse
    {
        $this->admin();
        $roles = Role::query()->where('roleName', '!=', 'CLIENT')->orderBy('roleName')->get()
            ->map(fn ($r) => ['roleName' => $r->roleName, 'roleLabel' => $r->roleLabel ?: ucwords(strtolower(str_replace('_', ' ', $r->roleName)))])->values();
        $facilities = Facility::query()->orderBy('facilityName')->get(['facilityId', 'facilityName', 'facilityState'])->values();
        $users = User::query()->with('facility')->orderBy('firstName')->limit(3000)->get()
            ->map(fn ($u) => [
                'id'       => $u->id,
                'name'     => trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')) ?: $u->email,
                'email'    => $u->email,
                'facility' => $u->facility->facilityName ?? null,
                'phones'   => 0,
            ])->values();
        $phones = StaffPushToken::where('active', true)->selectRaw('userId, COUNT(*) c')->groupBy('userId')->pluck('c', 'userId');
        $users = $users->map(fn ($u) => ['phones' => (int) ($phones[$u['id']] ?? 0)] + $u);

        return response()->json(['roles' => $roles, 'facilities' => $facilities, 'users' => $users, 'routes' => self::ROUTES]);
    }

    /** POST /staff-notifications/preview { audience } — how many people and phones it would reach. */
    public function preview(Request $request): JsonResponse
    {
        $this->admin();
        $audience = $this->audienceFrom($request);
        $users = StaffAudience::users($audience);
        $phones = StaffPushToken::whereIn('userId', $users->pluck('id'))->where('active', true)->count();
        return response()->json([
            'recipients' => $users->count(),
            'phones'     => $phones,
            'withApp'    => StaffPushToken::whereIn('userId', $users->pluck('id'))->where('active', true)->distinct('userId')->count('userId'),
            'audienceText' => StaffAudience::describe($audience),
            'sample'     => $users->take(8)->map(fn ($u) => trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')) ?: $u->email)->values(),
        ]);
    }

    /** POST /staff-notifications { title, body, route?, push?, audience } */
    public function send(Request $request): JsonResponse
    {
        $me = $this->admin();
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body'  => ['required', 'string', 'max:2000'],
            'route' => ['nullable', 'string', 'max:120'],
            'push'  => ['nullable', 'boolean'],
        ]);
        $audience = $this->audienceFrom($request);
        $users = StaffAudience::users($audience);
        abort_if($users->isEmpty(), 422, 'Nobody matches this audience.');

        $route = in_array($data['route'] ?? '', self::ROUTES, true) ? $data['route'] : '/notifications';
        $push = $request->has('push') ? (bool) $data['push'] : true;

        $b = StaffBroadcast::create([
            'title' => $data['title'], 'body' => $data['body'], 'route' => $route,
            'audience' => $audience, 'audienceText' => StaffAudience::describe($audience),
            'push' => $push, 'createdById' => $me->id,
        ]);
        $r = StaffPushService::notify($users->pluck('id')->all(), $data['title'], $data['body'], [
            'type' => 'message', 'route' => $route, 'broadcastId' => $b->id, 'push' => $push, 'data' => ['broadcastId' => $b->id],
        ]);
        $b->update(['recipients' => $r['recipients'], 'phones' => $r['phones']]);

        if (class_exists(ActivityLog::class)) {
            ActivityLog::record('staff_notification_sent', "Sent \"{$b->title}\" to {$b->audienceText} ({$r['recipients']} people, {$r['phones']} phones).", [
                'subjectType' => 'broadcast', 'subjectId' => $b->id, 'details' => ['audience' => $audience, 'push' => $push],
            ]);
        }

        return response()->json([
            'message' => "Sent to {$r['recipients']} " . ($r['recipients'] === 1 ? 'person' : 'people') . ($push ? " ({$r['phones']} " . ($r['phones'] === 1 ? 'phone' : 'phones') . ')' : ''),
            'broadcast' => $b,
        ], 201);
    }

    /** GET /staff-notifications?search=&status=active|withdrawn|all&page= — what was sent, newest first, with how many have read it. */
    public function index(Request $request): JsonResponse
    {
        $this->admin();
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', 'active');
        $page = StaffBroadcast::query()
            ->when($status === 'active', fn ($q) => $q->whereNull('withdrawnAt'))
            ->when($status === 'withdrawn', fn ($q) => $q->whereNotNull('withdrawnAt'))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', "%{$search}%")->orWhere('body', 'like', "%{$search}%")->orWhere('audienceText', 'like', "%{$search}%")))
            ->orderByDesc('id')
            ->paginate(min(100, max(10, (int) $request->query('perPage', 25))));
        $rows = collect($page->items());
        $read = StaffNotification::whereIn('broadcastId', $rows->pluck('id'))->whereNotNull('readAt')
            ->selectRaw('broadcastId, COUNT(*) c')->groupBy('broadcastId')->pluck('c', 'broadcastId');
        $delivered = StaffNotification::whereIn('broadcastId', $rows->pluck('id'))
            ->selectRaw('broadcastId, COUNT(*) c')->groupBy('broadcastId')->pluck('c', 'broadcastId');
        $names = User::whereIn('id', $rows->pluck('createdById')->merge($rows->pluck('editedById'))->merge($rows->pluck('lastResentById'))->filter()->unique())->get()
            ->mapWithKeys(fn ($u) => [$u->id => trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')) ?: $u->email]);

        return response()->json([
            'broadcasts' => $rows->map(fn ($b) => $this->row($b, (int) ($read[$b->id] ?? 0), (int) ($delivered[$b->id] ?? $b->recipients), $names))->values(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }

    private function row(StaffBroadcast $b, int $read, int $delivered, $names): array
    {
        return [
            'id' => $b->id, 'title' => $b->title, 'body' => $b->body, 'route' => $b->route,
            'audience' => $b->audience, 'audienceText' => $b->audienceText, 'push' => $b->push,
            'recipients' => $delivered, 'phones' => $b->phones, 'read' => $read, 'unread' => max(0, $delivered - $read),
            'sentBy' => $names[$b->createdById] ?? null, 'sentAt' => $b->created_at?->toIso8601String(),
            'editedAt' => $b->editedAt?->toIso8601String(), 'editedBy' => $names[$b->editedById] ?? null,
            'resendCount' => (int) $b->resendCount, 'lastResentAt' => $b->lastResentAt?->toIso8601String(), 'lastResentBy' => $names[$b->lastResentById] ?? null,
            'withdrawnAt' => $b->withdrawnAt?->toIso8601String(),
        ];
    }

    /** GET /staff-notifications/{id}/recipients?filter=all|read|unread — who got it and who has read it. */
    public function recipients(Request $request, int $id): JsonResponse
    {
        $this->admin();
        $b = StaffBroadcast::findOrFail($id);
        $filter = (string) $request->query('filter', 'all');
        $rows = StaffNotification::where('broadcastId', $b->id)
            ->when($filter === 'read', fn ($q) => $q->whereNotNull('readAt'))
            ->when($filter === 'unread', fn ($q) => $q->whereNull('readAt'))
            ->orderByRaw('readAt IS NULL')->orderByDesc('readAt')->limit(2000)->get(['userId', 'readAt', 'created_at']);
        $users = User::with('facility')->whereIn('id', $rows->pluck('userId'))->get()->keyBy('id');
        $phones = StaffPushToken::whereIn('userId', $rows->pluck('userId'))->where('active', true)->selectRaw('userId, COUNT(*) c')->groupBy('userId')->pluck('c', 'userId');
        return response()->json(['recipients' => $rows->map(function ($r) use ($users, $phones) {
            $u = $users->get($r->userId);
            return [
                'userId' => $r->userId,
                'name' => $u ? (trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')) ?: $u->email) : 'Deleted user',
                'email' => $u->email ?? null, 'facility' => $u?->facility?->facilityName,
                'readAt' => $r->readAt?->toIso8601String(), 'deliveredAt' => $r->created_at?->toIso8601String(),
                'hasApp' => (int) ($phones[$r->userId] ?? 0) > 0,
            ];
        })->values()]);
    }

    /**
     * PUT /staff-notifications/{id} { title, body, route? }
     * Corrects the text: the bell item already delivered to each person is updated too (read/unread is kept).
     * A phone notification already shown on a lock screen can't be changed — resend if staff must see the correction.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $me = $this->admin();
        $b = StaffBroadcast::findOrFail($id);
        abort_if($b->withdrawnAt, 422, 'This notification was withdrawn.');
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'body'  => ['required', 'string', 'max:2000'],
            'route' => ['nullable', 'string', 'max:120'],
        ]);
        $route = in_array($data['route'] ?? $b->route, self::ROUTES, true) ? ($data['route'] ?? $b->route) : '/notifications';
        $b->update(['title' => $data['title'], 'body' => $data['body'], 'route' => $route, 'editedAt' => now(), 'editedById' => $me->id]);
        $n = StaffNotification::where('broadcastId', $b->id)->update(['title' => mb_substr($data['title'], 0, 120), 'body' => $data['body'], 'route' => $route, 'updated_at' => now()]);
        $this->log('staff_notification_edited', "Edited staff notification \"{$b->title}\" ({$n} bell items updated).", $b);
        return response()->json(['message' => "Saved. The message was updated in {$n} " . ($n === 1 ? "person's" : "people's") . ' notification list.']);
    }

    /**
     * POST /staff-notifications/{id}/resend { to: all|unread, push?, title?, body?, route? }
     *  - all: everyone in the audience as it is now (staff who joined since are included);
     *  - unread: only people who haven't opened it yet.
     * Each person's earlier copy is replaced by a fresh unread one at the top of their list (no duplicates).
     */
    public function resend(Request $request, int $id): JsonResponse
    {
        $me = $this->admin();
        $b = StaffBroadcast::findOrFail($id);
        $data = $request->validate([
            'to'    => ['required', 'in:all,unread'],
            'push'  => ['nullable', 'boolean'],
            'title' => ['nullable', 'string', 'max:120'],
            'body'  => ['nullable', 'string', 'max:2000'],
            'route' => ['nullable', 'string', 'max:120'],
        ]);
        $title = $data['title'] ?? $b->title;
        $body = $data['body'] ?? $b->body;
        $route = in_array($data['route'] ?? $b->route, self::ROUTES, true) ? ($data['route'] ?? $b->route) : '/notifications';
        $push = array_key_exists('push', $data) && $data['push'] !== null ? (bool) $data['push'] : (bool) $b->push;

        if ($data['to'] === 'unread') {
            $userIds = StaffNotification::where('broadcastId', $b->id)->whereNull('readAt')->pluck('userId')->unique()->values()->all();
            abort_if(!$userIds, 422, 'Everyone has already read it.');
        } else {
            $userIds = StaffAudience::users((array) ($b->audience ?? []))->pluck('id')->all();
            abort_if(!$userIds, 422, 'Nobody matches this audience any more.');
        }

        // Replace earlier copies so nobody gets the same item twice in the bell.
        StaffNotification::where('broadcastId', $b->id)->whereIn('userId', $userIds)->delete();
        $r = StaffPushService::notify($userIds, $title, $body, [
            'type' => 'message', 'route' => $route, 'broadcastId' => $b->id, 'push' => $push, 'data' => ['broadcastId' => $b->id],
        ]);

        $edited = $title !== $b->title || $body !== $b->body || $route !== $b->route;
        if ($edited) {
            // Keep everyone's copy consistent with the new text.
            StaffNotification::where('broadcastId', $b->id)->update(['title' => mb_substr($title, 0, 120), 'body' => $body, 'route' => $route]);
        }
        $b->update([
            'title' => $title, 'body' => $body, 'route' => $route, 'push' => $push || $b->push,
            'recipients' => StaffNotification::where('broadcastId', $b->id)->count(),
            'phones' => max((int) $b->phones, $r['phones']),
            'resendCount' => (int) $b->resendCount + 1, 'lastResentAt' => now(), 'lastResentById' => $me->id,
            'withdrawnAt' => null,
        ] + ($edited ? ['editedAt' => now(), 'editedById' => $me->id] : []));

        $who = $data['to'] === 'unread' ? 'people who had not read it' : 'everyone';
        $this->log('staff_notification_resent', "Resent \"{$b->title}\" to {$who} ({$r['recipients']} people, {$r['phones']} phones).", $b);
        return response()->json([
            'message' => "Resent to {$r['recipients']} " . ($r['recipients'] === 1 ? 'person' : 'people') . ($push ? " ({$r['phones']} " . ($r['phones'] === 1 ? 'phone' : 'phones') . ')' : ''),
        ]);
    }

    /** DELETE /staff-notifications/{id} — withdraw: removes it from everyone's notification list (kept here as "withdrawn"). */
    public function withdraw(int $id): JsonResponse
    {
        $this->admin();
        $b = StaffBroadcast::findOrFail($id);
        $n = StaffNotification::where('broadcastId', $b->id)->delete();
        $b->update(['withdrawnAt' => now()]);
        $this->log('staff_notification_withdrawn', "Withdrew staff notification \"{$b->title}\" (removed from {$n} people's lists).", $b);
        return response()->json(['message' => "Withdrawn — removed from {$n} " . ($n === 1 ? "person's" : "people's") . ' notification list.']);
    }

    private function log(string $action, string $text, StaffBroadcast $b): void
    {
        if (class_exists(ActivityLog::class)) {
            try {
                ActivityLog::record($action, $text, ['subjectType' => 'broadcast', 'subjectId' => $b->id]);
            } catch (\Throwable $e) {
            }
        }
    }
}
