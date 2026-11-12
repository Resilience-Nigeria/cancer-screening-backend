<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BloomOtp extends Model
{
    protected $table = 'bloom_otps';

    protected $fillable = ['phone', 'purpose', 'code_hash', 'attempts', 'expires_at', 'consumed_at'];

    protected $casts = [
        'expires_at'  => 'datetime',
        'consumed_at' => 'datetime',
    ];
}
