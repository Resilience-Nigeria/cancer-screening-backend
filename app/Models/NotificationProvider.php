<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationProvider extends Model
{
    protected $primaryKey = 'providerId';

    protected $fillable = [
        'channel', 'providerKey', 'providerName', 'config', 'isActive', 'isDefault',
    ];

    protected $casts = [
        'config' => 'array',
        'isActive' => 'boolean',
        'isDefault' => 'boolean',
    ];

    public static function getDefault(string $channel): ?self
    {
        return static::where('channel', $channel)
            ->where('isDefault', true)
            ->where('isActive', true)
            ->first();
    }
}
