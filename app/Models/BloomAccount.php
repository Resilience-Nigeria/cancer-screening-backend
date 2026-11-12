<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * A person using the Bloom mobile app. Authenticated with the "bloom" JWT guard.
 */
class BloomAccount extends Authenticatable implements JWTSubject
{
    protected $table = 'bloom_accounts';

    protected $fillable = [
        'name', 'phone', 'email', 'password', 'age', 'need',
        'phone_verified_at', 'status', 'last_login_at',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'age'               => 'integer',
        'phone_verified_at' => 'datetime',
        'last_login_at'     => 'datetime',
    ];

    public function appointmentRequests(): HasMany
    {
        return $this->hasMany(BloomAppointmentRequest::class, 'account_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isVerified(): bool
    {
        return $this->phone_verified_at !== null;
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /** `typ` lets EnsureBloomAccount refuse any token that is not a Bloom token. */
    public function getJWTCustomClaims(): array
    {
        return ['typ' => 'bloom'];
    }
}
