<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientLoginToken extends Model
{
    protected $table = 'client_login_tokens';
    protected $primaryKey = 'tokenId';

    protected $fillable = [
        'token',
        'clientId',
        'expiresAt',
        'lastUsedAt',
    ];

    protected $casts = [
        'expiresAt' => 'datetime',
        'lastUsedAt' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'clientId', 'clientId');
    }

    public function isExpired(): bool
    {
        return $this->expiresAt->isPast();
    }
}
