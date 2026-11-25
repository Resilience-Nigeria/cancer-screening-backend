<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffDevice;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phones using the NCSR Mobile App.
 *  The app calls heartbeat when it opens / comes to the front; the answer can tell it to sign out or wipe itself.
 *  Admins (super admin / NICRAT admin) list phones and send "sign out" or "wipe" — applied the next time the phone connects.
 */
class StaffDeviceController extends Controller
{
    /** POST /mobile/devices/heartbeat { deviceId, platform?, model?, appVersion?, updateId?, pendingRecords? } */
    public function heartbeat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'deviceId' => ['required', 'string', 'max:64'],
            'platform' => ['nullable', 'string', 'max:20'],
            'model' => ['nullable', 'string', 'max:120'],
            'appVersion' => ['nullable', 'string', 'max:30'],
            'updateId' => ['nullable', 'string', 'max:64'],
            'pendingRecords' => ['nullable', 'integer', 'min:0'],
        ]);
        $d = StaffDevice::firstOrNew(['deviceId' => $data['deviceId']]);
        $d->fill($data + ['userId' => auth('api')->id(), 'lastSeenAt' => now(), 'lastIp' => $request->ip()]);
        $d->save();

        $action = $d->wipeRequestedAt && !$d->wipedAt ? 'wipe' : ($d->signOutRequestedAt && !$d->signedOutAt ? 'sign_out' : 'none');
        return response()->json(['action' => $action]);
    }

    /** POST /mobile/devices/done { deviceId, action: sign_out|wipe } — the phone confirms it did it. */
    public function done(Request $request): JsonResponse
    {
        $data = $request->validate(['deviceId' => ['required', 'string', 'max:64'], 'action' => ['required', 'in:sign_out,wipe']]);
        $d = StaffDevice::where('deviceId', $data['deviceId'])->firstOrFail();
        $data['action'] === 'wipe' ? $d->update(['wipedAt' => now(), 'signedOutAt' => now()]) : $d->update(['signedOutAt' => now()]);
        $this->log("Phone {$d->model} confirmed: " . ($data['action'] === 'wipe' ? 'data wiped' : 'signed out') . '.', $d);
        return response()->json(['message' => 'OK']);
    }

    private function admin(): User
    {
        $u = auth('api')->user();
        abort_if(!$u || !($u->isSuperAdmin() || $u->isNicratStaff()), 403, 'Only a super admin or NICRAT admin can manage phones.');
        return $u;
    }

    /** GET /mobile-admin/devices?search= */
    public function index(Request $request): JsonResponse
    {
        $this->admin();
        $rows = StaffDevice::orderByDesc('lastSeenAt')->limit(1000)->get();
        $users = User::with('facility')->whereIn('id', $rows->pluck('userId')->filter())->get()->keyBy('id');
        $search = mb_strtolower(trim((string) $request->query('search', '')));
        $out = $rows->map(function ($d) use ($users) {
            $u = $users->get($d->userId);
            return $d->toArray() + [
                'userName' => $u ? trim("{$u->firstName} {$u->lastName}") : null,
                'userEmail' => $u->email ?? null,
                'facility' => $u?->facility?->facilityName,
                'status' => $d->wipedAt ? 'wiped' : ($d->wipeRequestedAt ? 'wipe_pending' : ($d->signOutRequestedAt && !$d->signedOutAt ? 'sign_out_pending' : 'active')),
            ];
        })->filter(fn ($d) => $search === '' || str_contains(mb_strtolower("{$d['userName']} {$d['userEmail']} {$d['model']} {$d['facility']}"), $search))->values();
        return response()->json(['devices' => $out]);
    }

    /** POST /mobile-admin/devices/{id}/sign-out */
    public function signOut(int $id): JsonResponse
    {
        $me = $this->admin();
        $d = StaffDevice::findOrFail($id);
        $d->update(['signOutRequestedAt' => now(), 'signedOutAt' => null, 'requestedById' => $me->id]);
        $this->log("Requested sign-out of phone {$d->model}.", $d);
        return response()->json(['message' => 'The phone will be signed out the next time it connects. Unsynced records stay on it.']);
    }

    /** POST /mobile-admin/devices/{id}/wipe */
    public function wipe(int $id): JsonResponse
    {
        $me = $this->admin();
        $d = StaffDevice::findOrFail($id);
        $d->update(['wipeRequestedAt' => now(), 'wipedAt' => null, 'requestedById' => $me->id]);
        $this->log("Requested data wipe of phone {$d->model}.", $d);
        return response()->json(['message' => 'All NCSR data on the phone (including unsynced records) will be erased the next time it connects. Also reset the user\'s password if the phone was lost.']);
    }

    /** POST /mobile-admin/devices/{id}/cancel — cancel a pending sign-out / wipe */
    public function cancel(int $id): JsonResponse
    {
        $this->admin();
        $d = StaffDevice::findOrFail($id);
        $d->update(['signOutRequestedAt' => null, 'wipeRequestedAt' => null]);
        return response()->json(['message' => 'Cancelled.']);
    }

    private function log(string $text, StaffDevice $d): void
    {
        if (class_exists(\App\Models\ActivityLog::class)) {
            \App\Models\ActivityLog::record('mobile_device', $text, ['subjectType' => 'device', 'subjectId' => $d->id, 'details' => ['userId' => $d->userId, 'deviceId' => $d->deviceId]]);
        }
    }
}
