<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FacilityService extends Model
{
    protected $primaryKey = 'serviceId';
    protected $table = 'facility_services';

    protected $fillable = ['facilityId', 'serviceId', 'isActive'];

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'facilityServices', 'serviceId', 'facilityId')
            ->wherePivot('isActive', true);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'serviceId', 'serviceId');
    }
}