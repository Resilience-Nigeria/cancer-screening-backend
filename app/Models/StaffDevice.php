<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffDevice extends Model
{
    protected $table = 'staff_devices';
    protected $fillable = ['deviceId', 'userId', 'platform', 'model', 'appVersion', 'updateId', 'pendingRecords', 'lastSeenAt', 'lastIp',
        'signOutRequestedAt', 'wipeRequestedAt', 'requestedById', 'signedOutAt', 'wipedAt'];
    protected $casts = ['lastSeenAt' => 'datetime', 'signOutRequestedAt' => 'datetime', 'wipeRequestedAt' => 'datetime', 'signedOutAt' => 'datetime', 'wipedAt' => 'datetime'];
}
