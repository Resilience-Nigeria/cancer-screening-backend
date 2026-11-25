<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffPushToken extends Model
{
    protected $table = 'staff_push_tokens';
    protected $fillable = ['userId', 'token', 'platform', 'deviceName', 'appVersion', 'active', 'lastSeenAt', 'lastError'];
    protected $casts = ['active' => 'boolean', 'lastSeenAt' => 'datetime'];
}
