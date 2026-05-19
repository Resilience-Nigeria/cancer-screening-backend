<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $fillable = [
        'facilityId',
        'firstName',
        'lastName',
        'email',
        'phoneNumber',
        'alternatePhoneNumber',
        'password',
        'role',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facilityId', 'facilityId');
    }

    public function user_role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role', 'roleId');
    }

    public function screeningVisits(): HasMany
    {
        return $this->hasMany(ScreeningVisit::class, 'createdBy');
    }

    public function riskProfilesRecorded(): HasMany
    {
        return $this->hasMany(ClientRiskProfile::class, 'recorded_by');
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'facilityId' => $this->facilityId,
            'role' => $this->user_role?->roleName,
        ];
    }

    /**
     * Role Check Methods
     */
    public function isSuperAdmin(): bool
    {
        return $this->user_role?->roleName === 'SUPER_ADMIN';
    }

    public function isNicratStaff(): bool
    {
        return $this->user_role?->roleName === 'NICRAT_STAFF';
    }

    public function isHospitalAdmin(): bool
    {
        return $this->user_role?->roleName === 'HOSPITAL_ADMIN';
    }

    public function isDataClerk(): bool
    {
        return $this->user_role?->roleName === 'DATA_CLERK';
    }

    /**
     * Check if user has national-level access (can see all facilities)
     */
    public function hasNationalAccess(): bool
    {
        $roleName = $this->user_role?->roleName;
        return in_array($roleName, ['SUPER_ADMIN', 'NICRAT_STAFF']);
    }

    /**
     * Check if user has facility-level access (can only see their facility)
     */
    public function hasFacilityAccess(): bool
    {
        $roleName = $this->user_role?->roleName;
        return in_array($roleName, ['HOSPITAL_ADMIN', 'DATA_CLERK']);
    }

    /**
     * Check if user can create other users
     */
    public function canCreateUsers(): bool
    {
        $roleName = $this->user_role?->roleName;
        return in_array($roleName, ['SUPER_ADMIN', 'HOSPITAL_ADMIN']);
    }

    /**
     * Get roles this user can create
     * SUPER_ADMIN can create: NICRAT_STAFF, HOSPITAL_ADMIN
     * HOSPITAL_ADMIN can create: DATA_CLERK (nurses, doctors, staff)
     */
    public function getRolesCanCreate(): array
    {
        if ($this->isSuperAdmin()) {
            return Role::whereIn('roleName', ['NICRAT_STAFF', 'HOSPITAL_ADMIN'])->get()->toArray();
        }

        if ($this->isHospitalAdmin()) {
            return Role::where('roleName', 'DATA_CLERK')->get()->toArray();
        }

        return [];
    }

    /**
     * Scope: Filter by facility
     */
    public function scopeForFacility($query, $facilityId)
    {
        return $query->where('facilityId', $facilityId);
    }

    /**
     * Scope: Active users only
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}