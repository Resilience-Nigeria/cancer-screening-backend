<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'isScreeningCenter',
        'isTreatmentCenter',
        'latitude',
        'longitude',
        'facilityType',
        'facilityLevel',
        'parentFacilityId',
        'stagesSupported',
        'latitude',
        'longitude',
        'navigatorName',
        'navigatorPhone',
        'navigatorEmail',
        'whatsappNumber',
        'isActive',
    ];

    protected $casts = [
        'status' => 'string',
        'isScreeningCenter' => 'boolean',
        'isTreatmentCenter' => 'boolean',
        'facilityType' => 'array',
        'stagesSupported' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'isActive' => 'boolean',
    ];

    /**
     * The facility this one refers up to (a Feeder's parent is its
     * SubHub, a SubHub's parent is its Hub). Null for a top-level Hub.
     */
    public function parentFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'parentFacilityId', 'facilityId');
    }

    /**
     * Facilities that refer up to this one.
     */
    public function childFacilities(): HasMany
    {
        return $this->hasMany(Facility::class, 'parentFacilityId', 'facilityId');
    }

    /**
     * Whether this facility is configured to handle a given journey
     * stage (e.g. 'stage2', 'stage3'). Driven entirely by the
     * stagesSupported column — not inferred from facilityLevel — so an
     * admin can configure any facility's capabilities without a
     * code change.
     */
    public function supportsStage(string $stage): bool
    {
        return in_array($stage, $this->stagesSupported ?? [], true);
    }

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
     */
    public function getTotalScreeningsCountAttribute(): int
    {
        return \DB::table('screening_visits')
            ->whereIn('createdBy', $this->users()->pluck('id'))
            ->count();
    }

    /**
     * Get facility types as readable string
     */
    public function getFacilityTypesAttribute(): string
    {
        if ($this->isScreeningCenter && $this->isTreatmentCenter) {
            return 'Screening & Treatment Center';
        } elseif ($this->isScreeningCenter) {
            return 'Screening Center';
        } elseif ($this->isTreatmentCenter) {
            return 'Treatment Center';
        }
        return 'Not Specified';
    }

    /**
     * Get facility types as array
     */
    public function getFacilityTypesArrayAttribute(): array
    {
        $types = [];
        if ($this->isScreeningCenter) {
            $types[] = 'Screening Center';
        }
        if ($this->isTreatmentCenter) {
            $types[] = 'Treatment Center';
        }
        return $types;
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

    /**
     * Scope for screening centers
     */
    public function scopeScreeningCenters($query)
    {
        return $query->where('isScreeningCenter', true);
    }

    /**
     * Scope for treatment centers
     */
    public function scopeTreatmentCenters($query)
    {
        return $query->where('isTreatmentCenter', true);
    }

    /**
     * Scope for both screening and treatment centers
     */
    public function scopeBothTypes($query)
    {
        return $query->where('isScreeningCenter', true)
                     ->where('isTreatmentCenter', true);
    }
}