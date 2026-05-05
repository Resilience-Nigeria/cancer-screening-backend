<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Facility extends Model
{
    use HasFactory;
protected $primaryKey = 'facilityId';
    protected $fillable = [
        'facilityName',
        'facilityCode',
        'facilityState',
        'facilityLga',
        'facilityAddress',
        'phoneNumber',
        'email',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Get users belonging to this facility
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'facilityId');
    }


      public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'facilityId');
    }

    public function screeningVisits(): HasMany
    {
        return $this->hasMany(ScreeningVisit::class, 'facilityId');
    }


    /**
     * Get active users count
     */
    public function getActiveUsersCountAttribute(): int
    {
        return $this->users()->where('status', 'active')->count();
    }

    /**
     * Get total screenings count
     * Adjust based on your actual screening relationship
     */
    public function getTotalScreeningsCountAttribute(): int
    {
        // If you have a screenings relationship:
        // return $this->screenings()->count();
        
        // Or if screenings are through visits:
        return \DB::table('screening_visits')
            ->whereIn('createdBy', $this->users()->pluck('id'))
            ->count();
    }

    /**
     * Scope for active facilities
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for inactive facilities
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }
}