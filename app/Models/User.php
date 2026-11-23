<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
        'allFacilities',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'allFacilities' => 'boolean',
    ];

    /** Every role name, included whenever a user is sent to the frontend (e.g. at login). */
    protected $appends = ['roleNames'];

    /** Per-request cache of the resolved facility list. */
    private $visibleCache = false;

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'facilityId', 'facilityId');
    }

    /**
     * Facilities specifically granted to this individual user, on top
     * of whatever their role's dataScopeType already provides.
     * For "assigned" roles these ARE the facilities the person sees.
     */
    public function facilityGrants(): HasMany
    {
        return $this->hasMany(UserFacilityGrant::class, 'userId', 'id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    /** Main role (users.role). */
    public function user_role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role', 'roleId');
    }

    /** Extra roles (the main role is also stored here). */
    public function assignedRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role_assignments', 'userId', 'roleId');
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
            'roles' => $this->roleNames(),
        ];
    }

    // ── Roles ───────────────────────────────────────────────────────────────

    /** All of this person's roles: the main role first, then the others. */
    public function allRoles(): Collection
    {
        $roles = collect();
        if ($this->user_role) $roles->push($this->user_role);
        try {
            $roles = $roles->merge($this->assignedRoles);
        } catch (\Throwable $e) {
            // user_role_assignments not migrated yet — main role only
        }
        return $roles->filter()->unique('roleId')->values();
    }

    public function roleNames(): array
    {
        return $this->allRoles()->pluck('roleName')->filter()->values()->all();
    }

    public function getRoleNamesAttribute(): array
    {
        return $this->roleNames();
    }

    public function hasRole(string ...$names): bool
    {
        return (bool) array_intersect($names, $this->roleNames());
    }

    /**
     * Role Check Methods — true when ANY of the person's roles matches.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('NICRAT_SUPER_ADMIN');
    }

    public function isNicratStaff(): bool
    {
        return $this->hasRole('NICRAT_ADMIN');
    }

    public function isHospitalAdmin(): bool
    {
        return $this->hasRole('NAVIGATOR');
    }

    public function isDataClerk(): bool
    {
        return $this->hasRole('NURSE');
    }

    public function isPartner(): bool
    {
        return $this->hasRole('PARTNER');
    }

    public function isDoctor(): bool
    {
        return $this->hasRole('DOCTOR');
    }

    /**
     * The "Hospital Admin" role — not the same as isHospitalAdmin() above, which is a legacy-named
     * alias for NAVIGATOR.
     */
    public function isHospitalAdminRole(): bool
    {
        return $this->hasRole('HOSPITAL_ADMIN');
    }

    public function isNicratAdmin(): bool
    {
        return $this->isNicratStaff();
    }

    /** The NICRAT Staff role (all facilities or assigned facilities, per person). */
    public function isNicratStaffMember(): bool
    {
        return $this->hasRole('NICRAT_STAFF');
    }

    public function isNavigator(): bool
    {
        return $this->isHospitalAdmin();
    }

    public function isNurse(): bool
    {
        return $this->isDataClerk();
    }

    // ── Data scope ──────────────────────────────────────────────────────────

    /**
     * The widest scope across all of the person's roles. "assigned" with allFacilities counts as national.
     */
    public function dataScopeType(): ?string
    {
        $scopes = $this->allRoles()->pluck('dataScopeType')->filter()->unique()->values()->all();
        if (!$scopes) return $this->user_role?->dataScopeType;
        if (in_array('assigned', $scopes, true) && $this->allFacilities) return 'national';
        foreach (Role::SCOPE_ORDER as $s) {
            if (in_array($s, $scopes, true)) return $s;
        }
        return $scopes[0];
    }

    /** Can see every facility. */
    public function hasNationalAccess(): bool
    {
        return $this->dataScopeType() === 'national';
    }

    /** Limited to some facilities. */
    public function hasFacilityAccess(): bool
    {
        return !$this->hasNationalAccess();
    }

    /**
     * The facility IDs this user can see: null = all facilities, otherwise the union of what each role gives,
     * plus individually granted facilities.
     *   national          -> all
     *   assigned          -> all when "All facilities" is ticked for the person, else their facility + granted ones
     *   state / hub / subhub / facility_only -> measured from their own facility
     * No scope configured on any role fails open to all (a missed configuration, not a lockout).
     */
    public function visibleFacilityIds(): ?array
    {
        if ($this->visibleCache !== false) return $this->visibleCache;

        $roles = $this->allRoles();
        $scopes = $roles->pluck('dataScopeType')->all();

        if (!$roles->count() || in_array('national', $scopes, true) || in_array(null, $scopes, true)
            || (in_array('assigned', $scopes, true) && $this->allFacilities)) {
            return $this->visibleCache = null;
        }

        $facility = $this->facility;
        $ids = [];
        foreach (array_unique($scopes) as $scope) {
            if (!$facility) continue;
            $ids = array_merge($ids, match ($scope) {
                'state' => Facility::where('facilityState', $facility->facilityState)->pluck('facilityId')->toArray(),
                'hub_hierarchy' => ($hub = $facility->findAncestorAtLevel('hub')) ? $hub->descendantFacilityIds() : [$facility->facilityId],
                'subhub_hierarchy' => ($sub = $facility->findAncestorAtLevel('subhub')) ? $sub->descendantFacilityIds() : [$facility->facilityId],
                default => [$facility->facilityId],
            });
        }

        $granted = $this->facilityGrants()->pluck('facilityId')->toArray();

        return $this->visibleCache = array_values(array_unique(array_map('intval', [...$ids, ...$granted])));
    }

    /** Clears the cached facility list (after changing roles or facilities). */
    public function forgetVisibleFacilities(): void
    {
        $this->visibleCache = false;
    }

    public function canAccessFacility(?int $facilityId): bool
    {
        if (!$facilityId) return false;
        $visible = $this->visibleFacilityIds();
        return $visible === null || in_array((int) $facilityId, $visible, true);
    }

    // ── User management (from role settings) ────────────────────────────────

    /** Can open User Management. */
    public function canManageUsers(): bool
    {
        return $this->isSuperAdmin() || $this->allRoles()->contains(fn ($r) => (bool) $r->canManageUsers);
    }

    public function canCreateUsers(): bool
    {
        return !empty($this->manageableRoleIds());
    }

    /** Role IDs this person may give to others (Super Admin: every staff role except Super Admin). */
    public function manageableRoleIds(): array
    {
        if ($this->isSuperAdmin()) {
            return Role::where(fn ($q) => $q->whereNull('isStaffRole')->orWhere('isStaffRole', true))
                ->where('roleName', '!=', 'NICRAT_SUPER_ADMIN')->pluck('roleId')->map(fn ($v) => (int) $v)->all();
        }
        return $this->allRoles()->flatMap(fn ($r) => $r->manageableRoleIds ?? [])->map(fn ($v) => (int) $v)->unique()->values()->all();
    }

    public function getRolesCanCreate(): array
    {
        return Role::whereIn('roleId', $this->manageableRoleIds())->get()->toArray();
    }

    public function scopeForFacility($query, $facilityId)
    {
        return $query->where('facilityId', $facilityId);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
