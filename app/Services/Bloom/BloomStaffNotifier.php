<?php

namespace App\Services\Bloom;

use App\Models\BloomStaffNotification;
use Illuminate\Support\Facades\Log;

/** Adds an item to a health worker's notification bell. Never breaks the action that triggered it. */
class BloomStaffNotifier
{
    public static function notify(?int $userId, string $type, string $title, ?string $body = null, ?int $caseId = null): void
    {
        if (!$userId) {
            return;
        }
        try {
            BloomStaffNotification::create([
                'user_id' => $userId,
                'type'    => $type,
                'title'   => mb_substr($title, 0, 150),
                'body'    => $body !== null ? mb_substr($body, 0, 500) : null,
                'case_id' => $caseId,
            ]);
            // Also on the phone (lock screen), opening the bell when tapped.
            BloomPushService::toUser($userId, $title, $body, ['href' => '/chw/notifications', 'caseId' => $caseId]);
        } catch (\Throwable $e) {
            Log::warning('Bloom staff notification not saved', ['error' => $e->getMessage()]);
        }
    }
}
