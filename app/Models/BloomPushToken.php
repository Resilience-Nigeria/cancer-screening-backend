<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BloomPushToken extends Model
{
    protected $table = 'bloom_push_tokens';

    protected $fillable = ['token', 'app_role', 'account_id', 'user_id', 'platform', 'state', 'last_seen_at', 'disabled_at'];

    protected $casts = ['last_seen_at' => 'datetime', 'disabled_at' => 'datetime'];

    public function scopeActive($q)
    {
        return $q->whereNull('disabled_at');
    }
}
