<?php
// app/Models/OtpVerification.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    protected $primaryKey = 'otpId';

    protected $fillable = [
        'phoneNumber',
        'otp',
        'registrationId',
        'verified',
        'expiresAt',
    ];

    protected $casts = [
        'expiresAt' => 'datetime',
        'verified'  => 'boolean',
    ];

    public function isExpired(): bool
    {
        return $this->expiresAt->isPast();
    }
}