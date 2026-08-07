<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $primaryKey = 'serviceId';
    protected $table = 'services';

    protected $fillable = ['name', 'slug', 'category', 'isActive'];

    public function facilities(): BelongsToMany
    {
        return $this->belongsToMany(Facility::class, 'facility_services', 'serviceId', 'facilityId')
            ->wherePivot('facility_services.isActive', true);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'serviceId', 'serviceId');
    }
}