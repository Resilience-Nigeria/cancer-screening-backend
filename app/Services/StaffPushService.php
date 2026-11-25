<?php

namespace App\Services;

use App\Models\StaffNotification;
use App\Models\StaffPushToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Staff notifications: writes the bell item for each person and pushes it to their phones
 * through Expo's push service (https://exp.host/--/api/v2/push/send).
 * The push goes out after the web response is sent (no queue worker needed).
 */
class StaffPushService
{
    private const EXPO_URL = 'https://exp.host/--/api/v2/push/send';

    /**
     * @param int[] $userIds
     * @return array{recipients:int, phones:int}
     */
    public static function notify(array $userIds, string $title, ?string $body, array $opts = []): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds) return ['recipients' => 0, 'phones' => 0];

        $now = now();
        // 'bell' => false: phone notification only (e.g. chat messages, which have their own unread count)
        $rows = ($opts['bell'] ?? true) === false ? [] : array_map(fn ($uid) => [
            'userId'      => $uid,
            'type'        => $opts['type'] ?? 'message',
            'title'       => mb_substr($title, 0, 120),
            'body'        => $body,
            'route'       => $opts['route'] ?? null,
            'data'        => isset($opts['data']) ? json_encode($opts['data']) : null,
            'broadcastId' => $opts['broadcastId'] ?? null,
            'created_at'  => $now,
            'updated_at'  => $now,
        ], $userIds);
        foreach (array_chunk($rows, 500) as $chunk) {
            StaffNotification::insert($chunk);
        }

        if (($opts['push'] ?? true) === false) {
            return ['recipients' => count($userIds), 'phones' => 0];
        }

        $tokens = StaffPushToken::whereIn('userId', $userIds)->where('active', true)->pluck('token')->unique()->values()->all();
        if ($tokens) {
            $data = ['type' => $opts['type'] ?? 'message', 'route' => $opts['route'] ?? '/notifications'] + ($opts['data'] ?? []);
            dispatch(function () use ($tokens, $title, $body, $data) {
                StaffPushService::sendNow($tokens, $title, $body, $data);
            })->afterResponse();
        }

        return ['recipients' => count($userIds), 'phones' => count($tokens)];
    }

    /**
     * Sends to Expo now. Returns what Expo said for each phone (used by "send me a test").
     * @param string[] $tokens
     */
    public static function sendNow(array $tokens, string $title, ?string $body, array $data = []): array
    {
        $out = [];
        $access = config('ncsr_push.expo_access_token');
        foreach (array_chunk(array_values($tokens), 100) as $chunk) {
            $messages = array_map(fn ($t) => [
                'to'        => $t,
                'title'     => $title,
                'body'      => (string) ($body ?? ''),
                'data'      => (object) $data,
                'sound'     => 'default',
                'priority'  => 'high',
                'channelId' => 'default',
            ], $chunk);
            try {
                $req = Http::timeout(20)->acceptJson()->withHeaders(['Accept-Encoding' => 'gzip, deflate']);
                if ($access) $req = $req->withToken($access);
                $res = $req->post(self::EXPO_URL, $messages);
                if (!$res->ok()) {
                    Log::warning('Staff push: Expo returned ' . $res->status(), ['body' => mb_substr($res->body(), 0, 500)]);
                    foreach ($chunk as $t) $out[] = ['token' => $t, 'status' => 'error', 'message' => 'Expo returned ' . $res->status(), 'error' => null];
                    continue;
                }
                foreach (($res->json('data') ?? []) as $i => $ticket) {
                    $token = $chunk[$i] ?? null;
                    if (!$token) continue;
                    $err = $ticket['details']['error'] ?? null;
                    $out[] = ['token' => $token, 'status' => $ticket['status'] ?? 'unknown', 'message' => $ticket['message'] ?? null, 'error' => $err];
                    if (($ticket['status'] ?? '') === 'error') {
                        StaffPushToken::where('token', $token)->update([
                            'lastError' => mb_substr((string) ($ticket['message'] ?? $err), 0, 250),
                            'active'    => $err === 'DeviceNotRegistered' ? false : true,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Staff push failed: ' . $e->getMessage());
                foreach ($chunk as $t) $out[] = ['token' => $t, 'status' => 'error', 'message' => $e->getMessage(), 'error' => null];
            }
        }
        return $out;
    }
}
