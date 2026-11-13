<?php

namespace App\Http\Controllers\Api\Bloom;

use App\Http\Controllers\Controller;
use App\Models\BloomPushToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Phones register their Expo push token here.
 *  - Women: POST /bloom/push-token works signed in or not (signed-out phones still get articles/announcements).
 *  - Health workers: POST /bloom-chw/push-token with their staff token.
 * A token belongs to one phone, so registering again simply moves it to whoever is using the app now.
 */
class PushTokenController extends Controller
{
    use CaseConcerns;

    private const TOKEN_RULE = ['required', 'string', 'max:191', 'regex:/^(ExponentPushToken|ExpoPushToken)\[[^\]]+\]$/'];

    /** POST /bloom/push-token  { token, platform? } */
    public function registerWoman(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => self::TOKEN_RULE, 'platform' => ['nullable', 'in:ios,android']]);

        $this->save($data, [
            'app_role'   => 'woman',
            'account_id' => $this->bloomAccountId(),
            'user_id'    => null,
            'state'      => null,
        ]);

        return response()->json(['status' => true]);
    }

    /** POST /bloom-chw/push-token  { token, platform? } */
    public function registerChw(Request $request): JsonResponse
    {
        $me = $this->staff();
        if (!$this->isActiveNavigator($me)) {
            return $this->deny('Only verified community health workers can register for notifications.');
        }
        $data = $request->validate(['token' => self::TOKEN_RULE, 'platform' => ['nullable', 'in:ios,android']]);

        $this->save($data, [
            'app_role'   => 'chw',
            'account_id' => null,
            'user_id'    => $me->id,
            'state'      => $me->facility->facilityState ?? null,
        ]);

        return response()->json(['status' => true]);
    }

    /** DELETE /bloom/push-token and /bloom-chw/push-token  { token } — on sign-out. */
    public function unregister(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => self::TOKEN_RULE]);
        BloomPushToken::where('token', $data['token'])->delete();

        return response()->json(['status' => true]);
    }

    /**
     * POST /bloom/push-test  { token } — sends a test notification to this phone right now and returns
     * Expo's answer, so problems (missing Firebase key, uninstalled app, server can't reach exp.host) show up.
     */
    public function test(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => self::TOKEN_RULE]);
        $row = BloomPushToken::where('token', $data['token'])->first();
        if (!$row) {
            return response()->json(['status' => false, 'code' => 'NOT_REGISTERED', 'message' => 'This phone is not registered on the server yet.'], 404);
        }

        $href = $row->app_role === 'chw' ? '/chw/notifications' : '/notifications';
        $result = \App\Services\Bloom\BloomPushService::sendNow([$row->token], 'Bloom test notification', 'If you can see this, notifications are working on this phone.', ['href' => $href])[0] ?? null;

        if (!$result || $result['status'] !== 'ok') {
            return response()->json([
                'status'  => false,
                'code'    => $result['error'] ?? 'PUSH_FAILED',
                'message' => $result['message'] ?? 'Expo did not accept the notification.',
                'data'    => ['registeredAs' => $row->app_role, 'disabled' => $row->fresh()->disabled_at !== null],
            ], 502);
        }

        return response()->json(['status' => true, 'message' => 'Sent. It should arrive in a few seconds.', 'data' => ['registeredAs' => $row->app_role]]);
    }

    private function save(array $data, array $owner): void
    {
        BloomPushToken::updateOrCreate(['token' => $data['token']], $owner + [
            'platform'     => $data['platform'] ?? null,
            'last_seen_at' => now(),
            'disabled_at'  => null,
        ]);
    }

    /** The signed-in Bloom account, if a valid Bloom token came with the request (this route is public). */
    private function bloomAccountId(): ?int
    {
        try {
            $guard = Auth::guard('bloom');
            if ($guard->check() && $guard->payload()->get('typ') === 'bloom') {
                return (int) $guard->id();
            }
        } catch (\Throwable $e) {
            // signed out or expired token: register as an anonymous phone
        }

        return null;
    }
}
