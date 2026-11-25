<?php

namespace App\Support;

use App\Models\Facility;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Who a staff notification goes to.
 * Audience: { roles?: string[], facilityIds?: int[], userIds?: int[] }
 *  - userIds given      → exactly those people
 *  - otherwise          → every active staff member, narrowed by roles (any of them) and by facilities (own or granted)
 *  - nothing chosen     → everyone (all active staff)
 * Clients (the CLIENT role) never receive staff notifications.
 */
class StaffAudience
{
    /** @return Collection<int, User> */
    public static function users(array $audience): Collection
    {
        $userIds = array_values(array_filter(array_map('intval', $audience['userIds'] ?? [])));
        $roles = array_values(array_filter($audience['roles'] ?? []));
        $facilityIds = array_values(array_filter(array_map('intval', $audience['facilityIds'] ?? [])));

        $q = User::query()->with(['user_role', 'assignedRoles', 'facilityGrants']);
        if (Schema::hasColumn('users', 'status')) {
            $q->where(fn ($w) => $w->whereNull('status')->orWhere('status', 'active'));
        }
        if ($userIds) {
            $q->whereIn('id', $userIds);
        }

        return $q->get()
            ->filter(fn (User $u) => array_diff($u->roleNames(), ['CLIENT']) !== [] || $u->roleNames() === [])
            ->filter(fn (User $u) => !$roles || $u->hasRole(...$roles))
            ->filter(function (User $u) use ($facilityIds) {
                if (!$facilityIds) return true;
                $theirs = array_merge([(int) $u->facilityId], $u->facilityGrants->pluck('facilityId')->map(fn ($v) => (int) $v)->all());
                return (bool) array_intersect($facilityIds, $theirs);
            })
            ->values();
    }

    /** Staff at a facility (own facility or granted) — e.g. who hears about a referral sent there. */
    public static function atFacility(?int $facilityId): Collection
    {
        return $facilityId ? self::users(['facilityIds' => [$facilityId]]) : collect();
    }

    /** Super admins and NICRAT admins. */
    public static function admins(): Collection
    {
        return self::users(['roles' => ['NICRAT_SUPER_ADMIN', 'NICRAT_ADMIN']]);
    }

    /** "Nurses, Navigators at FMC Jabi" */
    public static function describe(array $audience): string
    {
        $userIds = array_filter($audience['userIds'] ?? []);
        if ($userIds) {
            $names = User::whereIn('id', $userIds)->get()->map(fn ($u) => trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')) ?: $u->email);
            return $names->count() <= 3 ? $names->implode(', ') : $names->take(3)->implode(', ') . ' and ' . ($names->count() - 3) . ' more';
        }
        $roles = array_filter($audience['roles'] ?? []);
        $facilityIds = array_filter($audience['facilityIds'] ?? []);
        $who = $roles
            ? Role::whereIn('roleName', $roles)->get()->map(fn ($r) => $r->roleLabel ?: ucwords(strtolower(str_replace('_', ' ', $r->roleName))))->implode(', ')
            : 'All staff';
        if ($facilityIds) {
            $f = Facility::whereIn('facilityId', $facilityIds)->pluck('facilityName');
            $who .= ' at ' . ($f->count() <= 2 ? $f->implode(' and ') : $f->take(2)->implode(', ') . ' and ' . ($f->count() - 2) . ' more facilities');
        }
        return mb_substr($who, 0, 250);
    }
}
