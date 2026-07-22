<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuVisibilityRule;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuVisibilityController extends Controller
{
    /**
     * Any authenticated user can read this — they need it to know which
     * menu items to render for their own role. Only the update below is
     * restricted.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'rules' => MenuVisibilityRule::orderBy('menuLabel')->get(),
            'roles' => Role::orderBy('roleName')->pluck('roleName'),
        ]);
    }

    /**
     * Bulk-update. Accepts { rules: { menuKey: [role, role, ...] | null } }
     * so the whole matrix can be saved in one request.
     *
     * null            = visible to everyone (no restriction)
     * [] (empty array) = restricted to nobody — only unchecked roles,
     *                     nothing assigned, so nobody can see it
     * [role, ...]      = visible only to the listed roles
     *
     * These are kept distinct rather than collapsing an empty array to
     * null, so unchecking every role actually means "nobody assigned"
     * rather than silently reverting to "everyone".
     */
    public function update(Request $request): JsonResponse
    {
        if (!$request->user()->isSuperAdmin()) {
            return response()->json(['message' => 'Only Super Admins can change menu visibility.'], 403);
        }

        $validated = $request->validate([
            'rules' => 'required|array',
        ]);

        $validRoles = Role::pluck('roleName')->all();

        foreach ($validated['rules'] as $menuKey => $roles) {
            $rule = MenuVisibilityRule::where('menuKey', $menuKey)->first();
            if (!$rule) {
                continue; // Ignore unknown keys rather than creating arbitrary rows
            }

            if ($roles === null) {
                $rule->update(['allowedRoles' => null]);
                continue;
            }

            $roles = array_values(array_intersect((array) $roles, $validRoles));
            $rule->update(['allowedRoles' => $roles]);
        }

        return response()->json(['message' => 'Menu visibility updated.']);
    }
}
