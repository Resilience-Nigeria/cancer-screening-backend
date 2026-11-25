<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppErrorReport;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\StaffPushService;
use App\Support\StaffAudience;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Help desk for the NCSR Mobile App: problem reports from staff, and crash / error reports sent by the app. */
class MobileSupportController extends Controller
{
    /** POST /mobile/support-tickets { category, message, device?, syncLog? } */
    public function report(Request $request): JsonResponse
    {
        $d = $request->validate([
            'category' => ['required', 'in:sync,login,form,results,stock,chat,bug,other'],
            'message' => ['required', 'string', 'min:5', 'max:4000'],
            'device' => ['nullable', 'array'],
            'syncLog' => ['nullable', 'array', 'max:300'],
        ]);
        $t = SupportTicket::create($d + ['userId' => auth('api')->id()]);
        $u = auth('api')->user();
        $who = trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')) ?: 'A user';
        StaffPushService::notify(StaffAudience::admins()->pluck('id')->all(), 'App problem reported', "{$who} ({$u->facility?->facilityName}): " . mb_substr($d['message'], 0, 140), [
            'type' => 'support', 'route' => '/notifications', 'push' => false, 'data' => ['ticketId' => $t->id],
        ]);
        return response()->json(['message' => 'Thank you — your report was sent to the NCSR support team.', 'id' => $t->id], 201);
    }

    /** POST /mobile/error-reports { reports: [{ message, stack?, screen?, fatal?, occurredAt?, deviceId?, platform?, appVersion?, updateId? }] } */
    public function errors(Request $request): JsonResponse
    {
        $d = $request->validate(['reports' => ['required', 'array', 'max:50'], 'reports.*.message' => ['required', 'string']]);
        foreach ($d['reports'] as $r) {
            $msg = mb_substr((string) $r['message'], 0, 500);
            $stack = isset($r['stack']) ? mb_substr((string) $r['stack'], 0, 8000) : null;
            AppErrorReport::create([
                'userId' => auth('api')->id(),
                'deviceId' => $r['deviceId'] ?? null, 'platform' => $r['platform'] ?? null,
                'appVersion' => $r['appVersion'] ?? null, 'updateId' => $r['updateId'] ?? null,
                'screen' => isset($r['screen']) ? mb_substr((string) $r['screen'], 0, 120) : null,
                'fatal' => (bool) ($r['fatal'] ?? false), 'message' => $msg, 'stack' => $stack,
                'fingerprint' => sha1($msg . '|' . mb_substr((string) $stack, 0, 300)),
                'occurredAt' => $r['occurredAt'] ?? now(),
            ]);
        }
        return response()->json(['message' => 'OK']);
    }

    private function admin(): User
    {
        $u = auth('api')->user();
        abort_if(!$u || !($u->isSuperAdmin() || $u->isNicratStaff()), 403, 'Only a super admin or NICRAT admin can see app reports.');
        return $u;
    }

    /** GET /mobile-admin/tickets?status=open|resolved|all */
    public function tickets(Request $request): JsonResponse
    {
        $this->admin();
        $status = (string) $request->query('status', 'open');
        $rows = SupportTicket::when($status !== 'all', fn ($q) => $q->where('status', $status))->orderByDesc('id')->limit(300)->get();
        $users = User::with('facility')->whereIn('id', $rows->pluck('userId')->filter())->get()->keyBy('id');
        return response()->json(['tickets' => $rows->map(fn ($t) => $t->toArray() + [
            'userName' => ($u = $users->get($t->userId)) ? trim("{$u->firstName} {$u->lastName}") : null,
            'userEmail' => $users->get($t->userId)?->email, 'userPhone' => $users->get($t->userId)?->phoneNumber,
            'facility' => $users->get($t->userId)?->facility?->facilityName,
        ])]);
    }

    /** POST /mobile-admin/tickets/{id}/resolve { note? } */
    public function resolve(Request $request, int $id): JsonResponse
    {
        $me = $this->admin();
        $t = SupportTicket::findOrFail($id);
        $note = (string) $request->input('note', '');
        $t->update(['status' => 'resolved', 'adminNote' => $note ?: null, 'resolvedById' => $me->id, 'resolvedAt' => now()]);
        if ($t->userId) {
            StaffPushService::notify([$t->userId], 'Your problem report was answered', $note ?: 'The NCSR support team marked your report as resolved.', ['type' => 'support', 'route' => '/notifications']);
        }
        return response()->json(['message' => 'Resolved.']);
    }

    /** GET /mobile-admin/errors?days=14 — grouped by error */
    public function errorSummary(Request $request): JsonResponse
    {
        $this->admin();
        $days = max(1, min((int) $request->query('days', 14), 90));
        $rows = AppErrorReport::where('created_at', '>=', now()->subDays($days))->orderByDesc('id')->limit(5000)->get();
        $groups = $rows->groupBy('fingerprint')->map(function ($g) {
            $f = $g->first();
            return [
                'fingerprint' => $f->fingerprint, 'message' => $f->message, 'stack' => $f->stack, 'screen' => $f->screen,
                'fatal' => $g->contains('fatal', true), 'count' => $g->count(), 'users' => $g->pluck('userId')->filter()->unique()->count(),
                'devices' => $g->pluck('deviceId')->filter()->unique()->count(), 'versions' => $g->pluck('appVersion')->filter()->unique()->values(),
                'lastSeen' => $g->max('created_at')?->toIso8601String(), 'firstSeen' => $g->min('created_at')?->toIso8601String(),
            ];
        })->sortByDesc('count')->values();
        return response()->json(['errors' => $groups, 'days' => $days]);
    }
}
