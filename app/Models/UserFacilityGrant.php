<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFacilityGrant extends Model
{
    protected $table = 'user_facility_grants';
    protected $primaryKey = 'grantId';

    protected $fillable = [
        'userId',
        'facilityId',
        'grantedBy',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userId', 'id');
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facilityId', 'facilityId');
    }
}
