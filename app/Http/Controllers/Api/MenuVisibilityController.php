<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuVisibilityRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuVisibilityController extends Controller
{
    /**
     * The full role list menu rules can be restricted to — kept here
     * rather than hardcoded into the frontend, so the admin UI always
     * matches the actual roles that exist.
     */
    public const ROLES = [
        'NICRAT_SUPER_ADMIN', 'NICRAT_ADMIN', 'NAVIGATOR',
        'HOSPITAL_ADMIN', 'NURSE', 'DOCTOR', 'PARTNER',
    ];

    /**
     * Any authenticated user can read this — they need it to know which
     * menu items to render for their own role. Only the update below is
     * restricted.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'rules' => MenuVisibilityRule::orderBy('menuLabel')->get(),
            'roles' => self::ROLES,
        ]);
    }

    /**
     * Bulk-update. Accepts { rules: { menuKey: [role, role, ...] | null } }
     * so the whole matrix can be saved in one request. An empty array or
     * null means "visible to everyone".
     */
    public function update(Request $request): JsonResponse
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Only Super Admins can change menu visibility.'], 403);
        }

        $validated = $request->validate([
            'rules' => 'required|array',
        ]);

        foreach ($validated['rules'] as $menuKey => $roles) {
            $rule = MenuVisibilityRule::where('menuKey', $menuKey)->first();
            if (!$rule) {
                continue; // Ignore unknown keys rather than creating arbitrary rows
            }

            $roles = is_array($roles) ? array_values(array_intersect($roles, self::ROLES)) : null;

            $rule->update(['allowedRoles' => empty($roles) ? null : $roles]);
        }

        return response()->json(['message' => 'Menu visibility updated.']);
    }
}
