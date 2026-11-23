<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A role and what it allows — configured on the Roles page, not in code.
 *  dataScopeType: national | state | hub_hierarchy | subhub_hierarchy | facility_only | assigned
 */
class Role extends Model
{
    use HasFactory;
    public $table = 'roles';
    protected $primaryKey = 'roleId';
    protected $fillable = [
        'roleName', 'roleId', 'roleType', 'roleDescription', 'dataScopeType',
        'roleLabel', 'isSystem', 'isStaffRole', 'requiresOrganization', 'canManageUsers', 'manageableRoleIds',
    ];

    protected $casts = [
        'isSystem' => 'boolean',
        'isStaffRole' => 'boolean',
        'requiresOrganization' => 'boolean',
        'canManageUsers' => 'boolean',
        'manageableRoleIds' => 'array',
    ];

    /** Scopes measured from the person's own facility — they need one. */
    public const FACILITY_SCOPES = ['state', 'hub_hierarchy', 'subhub_hierarchy', 'facility_only'];

    /** Widest first. */
    public const SCOPE_ORDER = ['national', 'state', 'hub_hierarchy', 'subhub_hierarchy', 'assigned', 'facility_only'];

    public function permissions()
    {
        return $this->belongsToMany(Permissions::class, 'role_permissions', 'roleId', 'permissionId');
    }

    public function label(): string
    {
        return $this->roleLabel ?: ucwords(strtolower(str_replace('_', ' ', (string) $this->roleName)));
    }

    public function needsFacility(): bool
    {
        return in_array($this->dataScopeType, self::FACILITY_SCOPES, true) && !$this->requiresOrganization;
    }

    public function toOption(): array
    {
        return [
            'roleId' => $this->roleId,
            'roleName' => $this->roleName,
            'label' => $this->label(),
            'roleDescription' => $this->roleDescription,
            'dataScopeType' => $this->dataScopeType,
            'needsFacility' => $this->needsFacility(),
            'usesAssignedFacilities' => $this->dataScopeType === 'assigned',
            'requiresOrganization' => (bool) $this->requiresOrganization,
            'canManageUsers' => (bool) $this->canManageUsers,
            'manageableRoleIds' => array_map('intval', $this->manageableRoleIds ?? []),
            'isSystem' => (bool) $this->isSystem,
            'isStaffRole' => $this->isStaffRole !== false,
        ];
    }
}
