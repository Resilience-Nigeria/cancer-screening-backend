<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffBroadcast extends Model
{
    protected $table = 'staff_broadcasts';
    protected $fillable = [
        'title', 'body', 'route', 'audience', 'audienceText', 'push', 'recipients', 'phones', 'createdById',
        'editedAt', 'editedById', 'resendCount', 'lastResentAt', 'lastResentById', 'withdrawnAt',
    ];
    protected $casts = [
        'audience' => 'array', 'push' => 'boolean',
        'editedAt' => 'datetime', 'lastResentAt' => 'datetime', 'withdrawnAt' => 'datetime',
    ];
}
