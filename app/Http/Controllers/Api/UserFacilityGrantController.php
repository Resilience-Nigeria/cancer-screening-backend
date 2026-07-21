<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserFacilityGrant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserFacilityGrantController extends Controller
{
    /**
     * Only a Super Admin, or a Navigator managing someone within their
     * own visible facility set, can view/change another user's grants.
     */
    protected function authorizeManage(Request $request, User $targetUser): ?JsonResponse
    {
        $actor = $request->user();

        if ($actor->isSuperAdmin()) {
            return null;
        }

        if ($actor->isNavigator() && $actor->canAccessFacility($targetUser->facilityId)) {
            return null;
        }

        return response()->json(['message' => 'You are not permitted to manage this user\'s facility access.'], 403);
    }

    public function index(Request $request, User $user): JsonResponse
    {
        if ($error = $this->authorizeManage($request, $user)) {
            return $error;
        }

        return response()->json([
            'grants' => $user->facilityGrants()->with('facility')->get(),
        ]);
    }

    public function store(Request $request, User $user): JsonResponse
    {
        if ($error = $this->authorizeManage($request, $user)) {
            return $error;
        }

        $request->validate([
            'facilityId' => 'required|integer|exists:facilities,facilityId',
        ]);

        $grant = UserFacilityGrant::firstOrCreate(
            ['userId' => $user->id, 'facilityId' => $request->facilityId],
            ['grantedBy' => $request->user()->id],
        );

        return response()->json([
            'message' => 'Facility access granted.',
            'grant' => $grant->load('facility'),
        ], 201);
    }

    public function destroy(Request $request, User $user, int $facilityId): JsonResponse
    {
        if ($error = $this->authorizeManage($request, $user)) {
            return $error;
        }

        UserFacilityGrant::where('userId', $user->id)
            ->where('facilityId', $facilityId)
            ->delete();

        return response()->json(['message' => 'Facility access removed.']);
    }
}
