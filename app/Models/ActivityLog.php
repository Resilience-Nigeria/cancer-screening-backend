<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * General activity / audit log (Client ID changes, deletions, deletion requests, restores, ...).
 * Write with ActivityLog::record(); a logging failure never breaks the action being logged.
 */
class ActivityLog extends Model
{
    protected $table = 'activity_logs';
    protected $primaryKey = 'logId';

    protected $fillable = [
        'action', 'subjectType', 'subjectId', 'clientId', 'facilityId',
        'description', 'details', 'userId', 'userName', 'ipAddress',
    ];

    protected $casts = ['details' => 'array'];

    public static function record(string $action, string $description, array $opts = []): void
    {
        try {
            $user = auth('api')->user();
            $name = null;
            if ($user) {
                $name = $user->fullName ?? $user->name ?? null;
                if (!$name) $name = trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? ''));
                $name = $name ?: ($user->email ?? null);
            }

            static::create([
                'action'      => $action,
                'subjectType' => $opts['subjectType'] ?? null,
                'subjectId'   => isset($opts['subjectId']) ? (string) $opts['subjectId'] : null,
                'clientId'    => $opts['clientId'] ?? null,
                'facilityId'  => $opts['facilityId'] ?? null,
                'description' => $description,
                'details'     => $opts['details'] ?? null,
                'userId'      => $user?->id,
                'userName'    => $name,
                'ipAddress'   => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
