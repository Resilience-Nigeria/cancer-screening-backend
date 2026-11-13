<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BloomStaffNotification extends Model
{
    protected $table = 'bloom_staff_notifications';

    protected $fillable = ['user_id', 'type', 'title', 'body', 'case_id', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];
}
