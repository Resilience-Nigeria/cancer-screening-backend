<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppErrorReport extends Model
{
    protected $table = 'app_error_reports';
    protected $fillable = ['userId', 'deviceId', 'platform', 'appVersion', 'updateId', 'screen', 'fatal', 'message', 'stack', 'fingerprint', 'occurredAt'];
    protected $casts = ['fatal' => 'boolean', 'occurredAt' => 'datetime'];
}
