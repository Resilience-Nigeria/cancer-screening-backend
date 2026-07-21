<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSuperAdmin()) {
            return response()->json(['message' => 'Only a Super Admin can view role configuration.'], 403);
        }

        return response()->json([
            'roles' => Role::orderBy('roleName')->get(),
        ]);
    }

    public function updateScope(Request $request, Role $role): JsonResponse
    {
        $user = $request->user();

        if (!$user->isSuperAdmin()) {
            return response()->json(['message' => 'Only a Super Admin can change role configuration.'], 403);
        }

        if ($role->roleName === 'CLIENT') {
            return response()->json([
                'message' => 'The Client role has no data-scope concept — it uses a separate, single-record portal login.',
            ], 422);
        }

        $request->validate([
            'dataScopeType' => 'required|in:national,state,hub_hierarchy,subhub_hierarchy,facility_only',
        ]);

        $role->update(['dataScopeType' => $request->dataScopeType]);

        return response()->json([
            'message' => 'Role data scope updated.',
            'role' => $role,
        ]);
    }
}
