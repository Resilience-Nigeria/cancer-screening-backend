<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AppRelease;
use App\Models\MobileAppSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Get the NCSR Mobile App" prompt, shown on the web app when a signed-in user opens it on a phone or tablet.
 * Admins turn it on/off and choose who sees it on the web (Mobile App → App versions → Install prompt).
 */
class MobileAppPromoController extends Controller
{
    private const KEY = 'install_prompt';
    private const ROLES = ['NICRAT_SUPER_ADMIN', 'NICRAT_ADMIN', 'HOSPITAL_ADMIN', 'NAVIGATOR', 'NURSE', 'DOCTOR', 'PARTNER'];
    private const DEFAULTS = [
        'enabled' => false,
        'roles' => null,          // null = everyone
        'message' => 'Record screenings, samples and results faster — even with no network — with the NCSR Mobile App.',
        'iosUrl' => null,         // App Store / TestFlight link once the iPhone version exists
        'snoozeDays' => 7,
    ];

    private function settings(): array
    {
        return MobileAppSetting::get(self::KEY, self::DEFAULTS);
    }

    /** GET /mobile/app-promo — what the web app should show this user on a phone/tablet. */
    public function forMe(): JsonResponse
    {
        /** @var User $u */
        $u = auth('api')->user();
        $s = $this->settings();
        $roleOk = empty($s['roles']) || collect($s['roles'])->contains(fn ($r) => $u->hasRole($r));
        $latest = AppRelease::where('published', true)->orderByDesc('versionCode')->first();
        return response()->json([
            'show' => (bool) $s['enabled'] && $roleOk,
            'message' => $s['message'],
            'snoozeDays' => (int) $s['snoozeDays'],
            'android' => $latest ? [
                'url' => url('/api/mobile/app-latest'),
                'version' => $latest->version,
                'size' => $latest->apkSize ? (int) $latest->apkSize : null,
                'package' => 'ng.resilience.ncsrfield',
                'scheme' => 'ncsrfield',
            ] : null,
            'ios' => !empty($s['iosUrl']) ? ['url' => $s['iosUrl']] : null,
        ]);
    }

    /** GET /mobile-admin/app-promo */
    public function show(): JsonResponse
    {
        $this->admin();
        return response()->json(['settings' => $this->settings(), 'roles' => self::ROLES]);
    }

    /** PUT /mobile-admin/app-promo { enabled, roles?, message?, iosUrl?, snoozeDays? } */
    public function update(Request $request): JsonResponse
    {
        $me = $this->admin();
        $d = $request->validate([
            'enabled' => ['required', 'boolean'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'in:' . implode(',', self::ROLES)],
            'message' => ['nullable', 'string', 'max:300'],
            'iosUrl' => ['nullable', 'url', 'max:500'],
            'snoozeDays' => ['nullable', 'integer', 'min:1', 'max:90'],
        ]);
        $s = array_merge($this->settings(), [
            'enabled' => (bool) $d['enabled'],
            'roles' => !empty($d['roles']) ? array_values(array_unique($d['roles'])) : null,
            'message' => trim((string) ($d['message'] ?? '')) ?: self::DEFAULTS['message'],
            'iosUrl' => $d['iosUrl'] ?? null,
            'snoozeDays' => (int) ($d['snoozeDays'] ?? 7),
        ]);
        MobileAppSetting::put(self::KEY, $s, $me->id);
        if (class_exists(ActivityLog::class)) {
            try {
                ActivityLog::record('mobile_app_prompt_updated', 'Turned the "Get the mobile app" prompt ' . ($s['enabled'] ? 'on' : 'off') . '.', ['subjectType' => 'mobile_app']);
            } catch (\Throwable $e) {
            }
        }
        return response()->json(['message' => $s['enabled'] ? 'The install prompt is on.' : 'The install prompt is off.', 'settings' => $s]);
    }

    private function admin(): User
    {
        $u = auth('api')->user();
        abort_if(!$u || !($u->isSuperAdmin() || $u->isNicratStaff()), 403, 'Only a super admin or NICRAT admin can change this.');
        return $u;
    }
}
