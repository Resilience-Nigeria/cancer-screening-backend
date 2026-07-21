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
        'organization_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facilityId', 'facilityId');
    }

    /**
     * Facilities specifically granted to this individual user, on top
     * of whatever their role's dataScopeType already provides.
     */
    public function facilityGrants(): HasMany
    {
        return $this->hasMany(UserFacilityGrant::class, 'userId', 'id');
    }

    public function organization()  // ← Add this
{
    return $this->belongsTo(Organization::class, 'organization_id');
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
        return $this->user_role?->roleName === 'NICRAT_SUPER_ADMIN';
    }

    public function isNicratStaff(): bool
    {
        return $this->user_role?->roleName === 'NICRAT_ADMIN';
    }

    public function isHospitalAdmin(): bool
    {
        return $this->user_role?->roleName === 'NAVIGATOR';
    }

    public function isDataClerk(): bool
    {
        return $this->user_role?->roleName === 'NURSE';
    }

    public function isPartner(): bool
    {
        return $this->user_role?->roleName === 'PARTNER';
    }

    public function isDoctor(): bool
    {
        return $this->user_role?->roleName === 'DOCTOR';
    }

    /**
     * The genuinely new "Hospital Admin" role — NOT the same as
     * isHospitalAdmin() above, which is a legacy-named alias for
     * NAVIGATOR left over from an earlier role rename. Hospital Admin
     * and Navigator are functionally equivalent for staff-management
     * purposes (see UserController) but are distinct role identities.
     */
    public function isHospitalAdminRole(): bool
    {
        return $this->user_role?->roleName === 'HOSPITAL_ADMIN';
    }

    // Clearer aliases matching the current role names — the isX() methods
    // above are kept as-is so existing call sites aren't broken by a
    // rename; new code should prefer these.
    public function isNicratAdmin(): bool
    {
        return $this->isNicratStaff();
    }

    public function isNavigator(): bool
    {
        return $this->isHospitalAdmin();
    }

    public function isNurse(): bool
    {
        return $this->isDataClerk();
    }

   
    /**
     * Check if user has national-level access (can see all facilities).
     * Driven entirely by the role's configured dataScopeType — not a
     * hardcoded role-name list — so changing a role's scope in the
     * database takes effect everywhere immediately.
     */
    public function hasNationalAccess(): bool
    {
        return $this->dataScopeType() === 'national';
    }

    /**
     * Check if user has facility-level access (can only see their facility)
     */
    public function hasFacilityAccess(): bool
    {
        $roleName = $this->user_role?->roleName;
        return in_array($roleName, ['NAVIGATOR', 'HOSPITAL_ADMIN', 'NURSE', 'DOCTOR']);
    }

    public function dataScopeType(): ?string
    {
        return $this->user_role?->dataScopeType;
    }

    /**
     * The set of facility IDs this user can see, resolved from their
     * role's configured dataScopeType and their own assigned facility.
     * Returns null for "national" (no restriction — don't filter at
     * all), otherwise an array to use with whereIn('facilityId', ...).
     *
     * Two users with the same role/scope type but different assigned
     * facilities get different results here — the scope TYPE is
     * role-level config, but the resolved facility SET is always
     * relative to this specific user's own facility.
     */
    public function visibleFacilityIds(): ?array
    {
        $scope = $this->dataScopeType();

        if ($scope === 'national' || $scope === null) {
            // No scope configured on the role at all — fail open to
            // "national" rather than silently locking everyone out,
            // since that's almost always a missed-configuration bug
            // rather than an intentional lockout.
            return null;
        }

        $facility = $this->facility;

        $roleBasedIds = !$facility
            ? []
            : match ($scope) {
                'state' => \App\Models\Facility::where('facilityState', $facility->facilityState)
                    ->pluck('facilityId')->toArray(),

                'hub_hierarchy' => ($hub = $facility->findAncestorAtLevel('hub'))
                    ? $hub->descendantFacilityIds()
                    : [$facility->facilityId],

                'subhub_hierarchy' => ($subhub = $facility->findAncestorAtLevel('subhub'))
                    ? $subhub->descendantFacilityIds()
                    : [$facility->facilityId],

                'facility_only' => [$facility->facilityId],

                default => [$facility->facilityId],
            };

        // Additional individually-granted facilities, on top of whatever
        // the role scope already provides — never subtracts, only adds.
        $grantedIds = $this->facilityGrants()->pluck('facilityId')->toArray();

        return array_values(array_unique([...$roleBasedIds, ...$grantedIds]));
    }

    /**
     * Single-record equivalent of visibleFacilityIds() — use this for
     * authorization checks (e.g. "can this user open this visit?")
     * rather than list filtering.
     */
    public function canAccessFacility(?int $facilityId): bool
    {
        if (!$facilityId) {
            return false;
        }

        $visible = $this->visibleFacilityIds();

        return $visible === null || in_array($facilityId, $visible, true);
    }

    /**
     * Check if user can create other users
     */
    public function canCreateUsers(): bool
    {
        $roleName = $this->user_role?->roleName;
        return in_array($roleName, ['NICRAT_SUPER_ADMIN', 'NAVIGATOR', 'HOSPITAL_ADMIN']);
    }

    /**
     * Get roles this user can create
     * NICRAT_SUPER_ADMIN can create: NICRAT_ADMIN, NAVIGATOR, HOSPITAL_ADMIN, PARTNER
     * NAVIGATOR / HOSPITAL_ADMIN can create: NURSE, DOCTOR
     */
    public function getRolesCanCreate(): array
    {
        if ($this->isSuperAdmin()) {
            return Role::whereIn('roleName', ['NICRAT_ADMIN', 'NAVIGATOR', 'HOSPITAL_ADMIN', 'PARTNER'])->get()->toArray();
        }

        if ($this->isHospitalAdmin() || $this->isHospitalAdminRole()) {
            return Role::whereIn('roleName', ['NURSE', 'DOCTOR'])->get()->toArray();
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