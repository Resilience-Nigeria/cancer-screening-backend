<?php

namespace App\Services\Bloom;

use App\Models\BloomPushToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends phone notifications through Expo's push service (https://exp.host/--/api/v2/push/send).
 * Sending runs after the HTTP response, so admins and health workers never wait for it.
 * Tokens Expo reports as DeviceNotRegistered (app removed) are switched off.
 *
 * Optional: set BLOOM_EXPO_ACCESS_TOKEN in .env if "enhanced push security" is turned on for the Expo project.
 */
class BloomPushService
{
    private const URL = 'https://exp.host/--/api/v2/push/send';
    private const CHUNK = 100;

    /** A health worker (users.id). */
    public static function toUser(?int $userId, string $title, ?string $body, array $data = []): void
    {
        if (!$userId) {
            return;
        }
        self::queue(BloomPushToken::active()->where('app_role', 'chw')->where('user_id', $userId)->pluck('token')->all(), $title, $body, $data);
    }

    /** A woman's Bloom account. */
    public static function toAccount(?int $accountId, string $title, ?string $body, array $data = []): void
    {
        if (!$accountId) {
            return;
        }
        self::queue(BloomPushToken::active()->where('app_role', 'woman')->where('account_id', $accountId)->pluck('token')->all(), $title, $body, $data);
    }

    /** Everyone in an audience: all | women | chw, optionally only health workers in a state. Returns the number of phones. */
    public static function broadcast(string $audience, ?string $state, string $title, ?string $body, array $data = []): int
    {
        $q = BloomPushToken::active();
        if ($audience === 'women') {
            $q->where('app_role', 'woman');
        } elseif ($audience === 'chw') {
            $q->where('app_role', 'chw');
        }
        if ($state) {
            $q->where('app_role', 'chw')->where('state', $state);
        }
        $tokens = $q->pluck('token')->unique()->values()->all();
        self::queue($tokens, $title, $body, $data);

        return count($tokens);
    }

    /** @param string[] $tokens */
    private static function queue(array $tokens, string $title, ?string $body, array $data): void
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if (!$tokens) {
            return;
        }
        $send = static fn () => self::sendNow($tokens, $title, $body, $data);

        try {
            // Runs once the response has gone back (no queue worker needed).
            dispatch($send)->afterResponse();
        } catch (\Throwable $e) {
            $send();
        }
    }

    /**
     * Sends straight away. Returns what Expo said for each phone (used by the "send a test" check).
     * @param string[] $tokens
     * @return array<int, array{token: string, status: string, message: ?string, error: ?string}>
     */
    public static function sendNow(array $tokens, string $title, ?string $body, array $data = []): array
    {
        $out = [];
        $headers = ['Accept' => 'application/json', 'Accept-Encoding' => 'gzip, deflate'];
        if ($key = config('bloom.push.expo_access_token')) {
            $headers['Authorization'] = 'Bearer ' . $key;
        }

        foreach (array_chunk($tokens, self::CHUNK) as $chunk) {
            $messages = array_map(fn ($t) => [
                'to'        => $t,
                'title'     => mb_substr($title, 0, 150),
                'body'      => $body !== null ? mb_substr($body, 0, 300) : null,
                'data'      => (object) $data,
                'sound'     => 'default',
                'priority'  => 'high',
                'channelId' => 'updates', // created by the app (high importance, shown on the lock screen)
            ], $chunk);

            try {
                $res = Http::withHeaders($headers)->timeout(20)->post(self::URL, $messages);
                if (!$res->ok()) {
                    Log::warning('Bloom push: Expo returned ' . $res->status(), ['body' => mb_substr($res->body(), 0, 500)]);
                    foreach ($chunk as $t) {
                        $out[] = ['token' => $t, 'status' => 'error', 'message' => 'Expo push service returned HTTP ' . $res->status() . ': ' . mb_substr($res->body(), 0, 200), 'error' => 'HTTP_' . $res->status()];
                    }
                    continue;
                }
                // Tickets come back in the same order as the messages.
                foreach ((array) $res->json('data') as $i => $ticket) {
                    $out[] = [
                        'token'   => $chunk[$i] ?? '',
                        'status'  => (string) ($ticket['status'] ?? 'unknown'),
                        'message' => $ticket['message'] ?? null,
                        'error'   => $ticket['details']['error'] ?? null,
                    ];
                    if (($ticket['status'] ?? null) === 'error' && (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered')) {
                        BloomPushToken::where('token', $chunk[$i] ?? '')->update(['disabled_at' => now()]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Bloom push failed', ['error' => $e->getMessage()]);
                foreach ($chunk as $t) {
                    $out[] = ['token' => $t, 'status' => 'error', 'message' => 'Could not reach exp.host from the server: ' . $e->getMessage(), 'error' => 'NETWORK'];
                }
            }
        }

        return $out;
    }
}
