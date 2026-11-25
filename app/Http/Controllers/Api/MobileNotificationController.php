<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffNotification;
use App\Models\StaffPushToken;
use App\Services\StaffPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** NCSR Mobile App: phone registration for push, and the in-app notification bell. */
class MobileNotificationController extends Controller
{
    /** POST /mobile/push-token { token, platform?, deviceName?, appVersion? } — this phone belongs to the signed-in user. */
    public function registerToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token'      => ['required', 'string', 'max:255'],
            'platform'   => ['nullable', 'string', 'max:20'],
            'deviceName' => ['nullable', 'string', 'max:120'],
            'appVersion' => ['nullable', 'string', 'max:30'],
        ]);
        $row = StaffPushToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'userId'     => auth('api')->id(),
                'platform'   => $data['platform'] ?? null,
                'deviceName' => $data['deviceName'] ?? null,
                'appVersion' => $data['appVersion'] ?? null,
                'active'     => true,
                'lastSeenAt' => now(),
                'lastError'  => null,
            ]
        );
        return response()->json(['message' => 'Phone registered for notifications.', 'id' => $row->id]);
    }

    /** DELETE /mobile/push-token { token } — on sign-out: this phone stops getting this person's notifications. */
    public function removeToken(Request $request): JsonResponse
    {
        $token = (string) $request->input('token', '');
        if ($token !== '') {
            StaffPushToken::where('token', $token)->where('userId', auth('api')->id())->update(['active' => false]);
        }
        return response()->json(['message' => 'Phone removed.']);
    }

    /** GET /mobile/notifications?page=&unread=1 */
    public function index(Request $request): JsonResponse
    {
        $uid = auth('api')->id();
        $page = StaffNotification::where('userId', $uid)
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('readAt'))
            ->orderByDesc('id')
            ->paginate(min(50, max(10, (int) $request->query('perPage', 30))));

        return response()->json([
            'data'         => collect($page->items())->map->toApp()->values(),
            'unread'       => StaffNotification::where('userId', $uid)->whereNull('readAt')->count(),
            'current_page' => $page->currentPage(),
            'last_page'    => $page->lastPage(),
            'total'        => $page->total(),
        ]);
    }

    /** GET /mobile/notifications/unread-count */
    public function unreadCount(): JsonResponse
    {
        return response()->json(['unread' => StaffNotification::where('userId', auth('api')->id())->whereNull('readAt')->count()]);
    }

    /** POST /mobile/notifications/{id}/read */
    public function markRead(int $id): JsonResponse
    {
        StaffNotification::where('id', $id)->where('userId', auth('api')->id())->whereNull('readAt')->update(['readAt' => now()]);
        return $this->unreadCount();
    }

    /** POST /mobile/notifications/read-all */
    public function markAllRead(): JsonResponse
    {
        StaffNotification::where('userId', auth('api')->id())->whereNull('readAt')->update(['readAt' => now()]);
        return response()->json(['unread' => 0]);
    }

    /** POST /mobile/push-test — sends a test notification to the signed-in user's own phones and reports what Expo said. */
    public function test(): JsonResponse
    {
        $uid = auth('api')->id();
        $tokens = StaffPushToken::where('userId', $uid)->where('active', true)->pluck('token')->all();
        StaffPushService::notify([$uid], 'Test notification', 'If you can see this on your phone, notifications are working.', ['type' => 'test', 'push' => false, 'route' => '/notifications']);
        if (!$tokens) {
            return response()->json(['phones' => 0, 'results' => [], 'message' => 'No phone is registered for you yet. Open the NCSR Mobile App (installed build, not Expo Go) and allow notifications.']);
        }
        $results = StaffPushService::sendNow($tokens, 'Test notification', 'If you can see this on your phone, notifications are working.', ['type' => 'test', 'route' => '/notifications']);
        return response()->json(['phones' => count($tokens), 'results' => $results]);
    }
}
