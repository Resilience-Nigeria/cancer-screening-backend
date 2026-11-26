<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use App\Services\StaffPushService;
use App\Support\StaffPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Staff directory (find colleagues at any facility) and one-to-one chat.
 * Who can find whom:
 *  - nurses and navigators see only other nurses and navigators (at any facility);
 *  - admins (super admin, NICRAT admin, hospital admin) and other roles see everyone.
 * A nurse or navigator can still reply when an admin starts a conversation with them.
 * Clients (CLIENT role) are never listed. New messages are pushed to the other person's phone.
 */
class StaffChatController extends Controller
{
    /** Roles that only see each other in the staff directory. */
    private const PEER_ROLES = ['NURSE', 'NAVIGATOR'];

    private function me(): User
    {
        return auth('api')->user();
    }

    /** True when this user only sees nurses and navigators. */
    private function peersOnly(User $u): bool
    {
        if ($u->isSuperAdmin() || $u->isNicratStaff() || $u->hasRole('HOSPITAL_ADMIN')) return false;
        foreach (self::PEER_ROLES as $r) {
            if ($u->hasRole($r)) return true;
        }
        return false;
    }

    /** Limit a users query to people holding one of these roles (main role or extra assigned roles). */
    private function withRoles($q, array $roles)
    {
        $hasRoleColumn = Schema::hasColumn('users', 'role');
        return $q->where(fn ($w) => $w->when($hasRoleColumn, fn ($x) => $x->orWhereIn('role', $roles))
            ->orWhereHas('user_role', fn ($r) => $r->whereIn('roleName', $roles))
            ->orWhereHas('assignedRoles', fn ($r) => $r->whereIn('roleName', $roles)));
    }

    private function isPeer(User $u): bool
    {
        foreach (self::PEER_ROLES as $r) {
            if ($u->hasRole($r)) return true;
        }
        return false;
    }

    private function existingChat(int $a, int $b): ?ChatConversation
    {
        return ChatConversation::where('userA', min($a, $b))->where('userB', max($a, $b))->first();
    }

    /** May $me see / message $other? */
    private function canReach(User $me, User $other): bool
    {
        return !$this->peersOnly($me) || $this->isPeer($other) || $this->existingChat($me->id, $other->id) !== null;
    }

    private function card(User $u): array
    {
        $roles = $u->allRoles();
        return [
            'id' => $u->id,
            'name' => trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')) ?: $u->email,
            'firstName' => $u->firstName,
            'roles' => $roles->map(fn ($r) => $r->roleLabel ?: ucwords(strtolower(str_replace('_', ' ', $r->roleName))))->values(),
            'facility' => $u->facility->facilityName ?? null,
            'facilityState' => $u->facility->facilityState ?? null,
            'facilityId' => $u->facilityId,
            'email' => $u->email,
            'phone' => $u->phoneNumber,
            'photoUrl' => StaffPhoto::url($u),
        ];
    }

    /** GET /mobile/staff?search=&facilityId=&role=&state=&page= */
    public function directory(Request $request): JsonResponse
    {
        $me = $this->me();
        $search = trim((string) $request->query('search', ''));
        $q = User::query()->with(['facility', 'user_role', 'assignedRoles'])
            ->where('id', '!=', $me->id)
            ->when(Schema::hasColumn('users', 'status'), fn ($x) => $x->where(fn ($w) => $w->whereNull('status')->orWhere('status', 'active')))
            ->when($request->filled('facilityId'), fn ($x) => $x->where('facilityId', (int) $request->facilityId))
            ->when($request->filled('state'), fn ($x) => $x->whereHas('facility', fn ($f) => $f->where('facilityState', $request->state)))
            ->when($search !== '', fn ($x) => $x->where(fn ($w) => $w->where('firstName', 'like', "%{$search}%")->orWhere('lastName', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")->orWhereHas('facility', fn ($f) => $f->where('facilityName', 'like', "%{$search}%"))))
            ->orderBy('firstName');
        if ($this->peersOnly($me)) $this->withRoles($q, self::PEER_ROLES);
        $role = strtoupper((string) $request->query('role', ''));
        if ($role !== '') $this->withRoles($q, [$role]);
        $page = $q->paginate(30);
        $rows = collect($page->items())
            ->filter(fn ($u) => array_diff($u->roleNames(), ['CLIENT']) !== [])
            ->map(fn ($u) => $this->card($u))->values();
        return response()->json([
            'data' => $rows, 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(),
            'peersOnly' => $this->peersOnly($me),
        ]);
    }

    /** GET /mobile/staff/{id} */
    public function profile(int $id): JsonResponse
    {
        $u = User::with(['facility', 'user_role', 'assignedRoles'])->findOrFail($id);
        abort_if(array_diff($u->roleNames(), ['CLIENT']) === [], 404);
        abort_if(!$this->canReach($this->me(), $u), 404);
        return response()->json(['staff' => $this->card($u)]);
    }

    private function conversation(int $id): ChatConversation
    {
        $c = ChatConversation::findOrFail($id);
        $me = $this->me()->id;
        abort_if($c->userA !== $me && $c->userB !== $me, 403, 'Not your conversation.');
        return $c;
    }

    /** GET /mobile/chats — my conversations, newest first, with unread counts */
    public function index(): JsonResponse
    {
        $me = $this->me()->id;
        $convs = ChatConversation::where('userA', $me)->orWhere('userB', $me)->orderByDesc('lastMessageAt')->limit(200)->get();
        $others = User::with(['facility', 'user_role', 'assignedRoles'])->whereIn('id', $convs->map(fn ($c) => $c->otherThan($me)))->get()->keyBy('id');
        $last = ChatMessage::whereIn('id', ChatMessage::selectRaw('MAX(id)')->whereIn('conversationId', $convs->pluck('id'))->groupBy('conversationId'))->get()->keyBy('conversationId');
        $unread = ChatMessage::whereIn('conversationId', $convs->pluck('id'))->where('senderId', '!=', $me)->whereNull('readAt')
            ->selectRaw('conversationId, COUNT(*) n')->groupBy('conversationId')->pluck('n', 'conversationId');
        return response()->json(['chats' => $convs->map(function ($c) use ($me, $others, $last, $unread) {
            $o = $others->get($c->otherThan($me));
            $m = $last->get($c->id);
            return [
                'id' => $c->id,
                'with' => $o ? $this->card($o) : ['id' => $c->otherThan($me), 'name' => 'Former staff member'],
                'lastMessage' => $m ? ['body' => $m->body, 'mine' => $m->senderId === $me, 'at' => $m->created_at?->toIso8601String()] : null,
                'unread' => (int) ($unread[$c->id] ?? 0),
            ];
        })->values(), 'unread' => (int) $unread->sum()]);
    }

    /** GET /mobile/chats/unread-count */
    public function unreadCount(): JsonResponse
    {
        $me = $this->me()->id;
        $ids = ChatConversation::where('userA', $me)->orWhere('userB', $me)->pluck('id');
        return response()->json(['unread' => ChatMessage::whereIn('conversationId', $ids)->where('senderId', '!=', $me)->whereNull('readAt')->count()]);
    }

    /** POST /mobile/chats { userId } — open (or create) the conversation with someone */
    public function open(Request $request): JsonResponse
    {
        $meUser = $this->me();
        $me = $meUser->id;
        $other = (int) $request->validate(['userId' => ['required', 'integer', 'exists:users,id']])['userId'];
        abort_if($other === $me, 422, 'You cannot chat with yourself.');
        $o = User::with(['facility', 'user_role', 'assignedRoles'])->find($other);
        abort_if(!$this->canReach($meUser, $o), 403, 'You can message other nurses and navigators. To reach an administrator, use Help → Report a problem.');
        $c = ChatConversation::firstOrCreate(['userA' => min($me, $other), 'userB' => max($me, $other)]);
        return response()->json(['chat' => ['id' => $c->id, 'with' => $this->card($o)]]);
    }

    /** GET /mobile/chats/{id}/messages?after=&before= */
    public function messages(Request $request, int $id): JsonResponse
    {
        $c = $this->conversation($id);
        $me = $this->me()->id;
        $q = ChatMessage::where('conversationId', $c->id);
        if ($request->filled('after')) {
            $rows = $q->where('id', '>', (int) $request->after)->orderBy('id')->limit(200)->get();
        } else {
            $rows = $q->when($request->filled('before'), fn ($x) => $x->where('id', '<', (int) $request->before))->orderByDesc('id')->limit(50)->get()->reverse()->values();
        }
        $o = User::with(['facility', 'user_role', 'assignedRoles'])->find($c->otherThan($me));
        return response()->json([
            'with' => $o ? $this->card($o) : null,
            'messages' => $rows->map(fn ($m) => ['id' => $m->id, 'body' => $m->body, 'mine' => $m->senderId === $me, 'clientId' => $m->clientId,
                'clientMsgId' => $m->clientMsgId, 'at' => $m->created_at?->toIso8601String(), 'read' => $m->readAt !== null])->values(),
        ]);
    }

    /** POST /mobile/chats/{id}/messages { body, clientId?, clientMsgId? } */
    public function send(Request $request, int $id): JsonResponse
    {
        $c = $this->conversation($id);
        $me = $this->me();
        $d = $request->validate(['body' => ['required', 'string', 'max:4000'], 'clientId' => ['nullable', 'string', 'max:60'], 'clientMsgId' => ['nullable', 'string', 'max:64']]);
        if (!empty($d['clientMsgId']) && ($dup = ChatMessage::where('senderId', $me->id)->where('clientMsgId', $d['clientMsgId'])->first())) {
            return response()->json(['message' => ['id' => $dup->id, 'body' => $dup->body, 'mine' => true, 'at' => $dup->created_at?->toIso8601String()]]);
        }
        $m = ChatMessage::create($d + ['conversationId' => $c->id, 'senderId' => $me->id]);
        $c->update(['lastMessageAt' => now()]);
        $name = trim(($me->firstName ?? '') . ' ' . ($me->lastName ?? '')) ?: 'A colleague';
        StaffPushService::notify([$c->otherThan($me->id)], $name, mb_substr($d['body'], 0, 140), [
            'type' => 'chat', 'route' => "/chats/{$c->id}", 'bell' => false, 'data' => ['chatId' => $c->id],
        ]);
        return response()->json(['message' => ['id' => $m->id, 'body' => $m->body, 'mine' => true, 'clientMsgId' => $m->clientMsgId, 'at' => $m->created_at?->toIso8601String()]], 201);
    }

    /** POST /mobile/chats/{id}/read */
    public function read(int $id): JsonResponse
    {
        $c = $this->conversation($id);
        ChatMessage::where('conversationId', $c->id)->where('senderId', '!=', $this->me()->id)->whereNull('readAt')->update(['readAt' => now()]);
        return $this->unreadCount();
    }
}
