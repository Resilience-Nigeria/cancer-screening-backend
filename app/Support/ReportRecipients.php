<?php

namespace App\Support;

use App\Models\ReportRecipient;
use App\Models\ReportSetting;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Turns the rows in report_recipients into the actual list of people who get a report, and the
 * facilities each one sees.
 *
 *  - role row  : every active staff member with that role (with an email)
 *  - user row  : one staff member; with isExcluded = true they never get it, even if their role does
 *  - email row : any address (management, partners) — scope must be "all" or chosen facilities
 *
 * Scope:
 *  - own        : the facilities the person is responsible for
 *                   overdue_results -> Super/NICRAT admin all, hospital admin their facilities, others their own facility
 *                   weekly_summary  -> their role's data scope (all for national roles)
 *  - all        : every facility
 *  - facilities : the facilities chosen on the row
 * Someone covered by several rows gets one email covering all of their facilities.
 */
class ReportRecipients
{
    public const REPORTS = [
        'weekly_summary'  => 'Weekly summary report',
        'overdue_results' => 'Overdue test results reminder',
    ];

    public static function activeUsers(): Collection
    {
        return User::with(['user_role', 'assignedRoles'])->get()->filter(function ($u) {
            $status = strtolower((string) ($u->status ?? 'active'));
            return !empty($u->email) && !in_array($status, ['inactive', 'disabled', 'suspended'], true);
        });
    }

    /** Facilities a person is responsible for, for this report (null = all). */
    public static function ownFacilities(string $reportKey, $user): ?array
    {
        if ($reportKey === 'overdue_results') {
            return OverdueResults::facilitiesFor($user);
        }
        if (method_exists($user, 'hasNationalAccess') && $user->hasNationalAccess()) return null;
        $ids = $user->visibleFacilityIds();
        if ($ids === null) return null;
        return $ids ?: ($user->facilityId ? [(int) $user->facilityId] : []);
    }

    /** Facilities for one recipient row (null = all). */
    private static function rowFacilities(string $reportKey, ReportRecipient $row, $user = null): ?array
    {
        if ($row->scope === 'all') return null;
        if ($row->scope === 'facilities') return array_values(array_map('intval', $row->facilityIds ?? []));
        return $user ? self::ownFacilities($reportKey, $user) : [];
    }

    /** null (all) wins; otherwise the union. */
    private static function merge(?array $a, ?array $b, bool $first): ?array
    {
        if ($first) return $b;
        if ($a === null || $b === null) return null;
        return array_values(array_unique(array_merge($a, $b)));
    }

    /**
     * Everyone who gets this report:
     * [ ['email','name','facilities'=>?array,'user'=>?User,'via'=>[reasons]], ... ] keyed by lower-case email.
     */
    public static function resolve(string $reportKey): array
    {
        $rows = ReportRecipient::where('reportKey', $reportKey)->where('active', true)->get();
        if ($rows->isEmpty()) return [];

        $users = self::activeUsers();
        $byId = $users->keyBy('id');
        $excluded = $rows->where('type', 'user')->where('isExcluded', true)->pluck('userId')->map(fn ($v) => (int) $v)->all();

        $out = [];
        $add = function (string $email, ?string $name, ?array $facilities, $user, string $via) use (&$out) {
            $k = strtolower(trim($email));
            if ($k === '' || !filter_var($k, FILTER_VALIDATE_EMAIL)) return;
            $first = !isset($out[$k]);
            $out[$k] ??= ['email' => trim($email), 'name' => $name ?: $email, 'facilities' => [], 'user' => $user, 'via' => []];
            $out[$k]['facilities'] = self::merge($out[$k]['facilities'], $facilities, $first);
            $out[$k]['via'][] = $via;
            if ($user && !$out[$k]['user']) $out[$k]['user'] = $user;
        };
        $nameOf = fn ($u) => trim(($u->firstName ?? '') . ' ' . ($u->lastName ?? '')) ?: $u->email;

        foreach ($rows as $row) {
            if ($row->type === 'role') {
                foreach ($users as $u) {
                    // Any of the person's roles counts (people can hold several roles).
                    $names = method_exists($u, 'roleNames') ? $u->roleNames() : [$u->user_role->roleName ?? null];
                    if (!in_array($row->roleName, $names, true) || in_array((int) $u->id, $excluded, true)) continue;
                    $add($u->email, $nameOf($u), self::rowFacilities($reportKey, $row, $u), $u, 'Role: ' . self::roleLabel($row->roleName));
                }
            } elseif ($row->type === 'user' && !$row->isExcluded) {
                $u = $byId->get((int) $row->userId);
                if ($u && !in_array((int) $u->id, $excluded, true)) {
                    $add($u->email, $nameOf($u), self::rowFacilities($reportKey, $row, $u), $u, 'Added by name');
                }
            } elseif ($row->type === 'email' && $row->email) {
                $add($row->email, $row->name, self::rowFacilities($reportKey, $row), null, 'Email address');
            }
        }

        return $out;
    }

    /**
     * Facilities this signed-in user sees for the report (null = all), or false when they are not a recipient.
     * Used by the in-app overdue pop-up when "apply to the pop-up" is switched on.
     */
    public static function forUser(string $reportKey, $user)
    {
        $all = self::resolve($reportKey);
        $k = strtolower(trim((string) $user->email));
        return isset($all[$k]) ? $all[$k]['facilities'] : false;
    }

    /** The role's display name from the Roles page, or a tidied-up code. */
    public static function roleLabel(?string $role): string
    {
        static $labels = null;
        if ($labels === null) {
            try {
                $labels = \Illuminate\Support\Facades\Schema::hasColumn('roles', 'roleLabel')
                    ? \Illuminate\Support\Facades\DB::table('roles')->whereNotNull('roleLabel')->pluck('roleLabel', 'roleName')->all()
                    : [];
            } catch (\Throwable $e) {
                $labels = [];
            }
        }
        return $labels[$role] ?? ucwords(strtolower(str_replace('_', ' ', (string) $role)));
    }

    /** Short description of a facility list, for the settings page. */
    public static function scopeText(?array $facilities, ?Collection $names = null): string
    {
        if ($facilities === null) return 'All facilities';
        if (!$facilities) return 'No facility';
        if ($names) {
            $list = collect($facilities)->map(fn ($id) => $names[$id] ?? "#{$id}");
            return $list->count() > 3 ? $list->take(3)->implode(', ') . ' +' . ($list->count() - 3) . ' more' : $list->implode(', ');
        }
        return count($facilities) . ' facilit' . (count($facilities) === 1 ? 'y' : 'ies');
    }

    public static function setting(string $reportKey): ReportSetting
    {
        return ReportSetting::for($reportKey);
    }
}
