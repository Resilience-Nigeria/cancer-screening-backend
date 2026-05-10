<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Facility;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Get all users with filters (respects role hierarchy)
     */
    public function index(Request $request): JsonResponse
    {
        $currentUser = Auth::user();
        $query = User::with(['facility']);

        // Super admin sees all users
        // Facility admin sees only users in their facility
        if ($currentUser->role !== 'SUPER_ADMIN') {
            $query->where('facilityId', $currentUser->facilityId);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('firstName', 'like', "%{$search}%")
                  ->orWhere('lastName', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phoneNumber', 'like', "%{$search}%");
            });
        }

        // Filter by facility (only super admin can filter by facility)
        if ($request->has('facility') && $request->facility !== 'all' && $currentUser->role === 'SUPER_ADMIN') {
            $query->where('facilityId', $request->facility);
        }

        // Filter by role
        if ($request->has('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $users = $query->get()->map(function ($user) {
            return [
                'id' => $user->id,
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
                'email' => $user->email,
                'phoneNumber' => $user->phoneNumber,
                'alternatePhoneNumber' => $user->alternatePhoneNumber,
                'role' => $user->user_role->roleName,
                'status' => $user->status,
                'facilityId' => $user->facilityId,
                'facility' => $user->facility ? [
                    'id' => $user->facility->id,
                    'facilityName' => $user->facility->facilityName,
                    'facilityCode' => $user->facility->facilityCode,
                ] : null,
                'createdAt' => $user->created_at,
                'updatedAt' => $user->updated_at,
            ];
        });

        // Calculate stats based on user's access
        if ($currentUser->role === 'SUPER_ADMIN') {
            $statsQuery = User::query();
        } else {
            $statsQuery = User::where('facilityId', $currentUser->facilityId);
        }

        $stats = [
            'total' => $statsQuery->count(),
            'active' => $statsQuery->where('status', 'active')->count(),
            'inactive' => $statsQuery->where('status', 'inactive')->count(),
            'byRole' => $statsQuery->select('role', \DB::raw('count(*) as total'))
                ->groupBy('role')
                ->pluck('total', 'role')
                ->toArray(),
        ];

        return response()->json([
            'status' => true,
            'users' => $users,
            'stats' => $stats,
        ]);
    }

    /**
     * Get a single user
     */
    public function show(User $user): JsonResponse
    {
        $currentUser = Auth::user();

        // Check access: super admin sees all, facility admin sees only their facility
        if ($currentUser->role !== 'SUPER_ADMIN' && $user->facilityId !== $currentUser->facilityId) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access',
            ], 403);
        }

        $user->load('facility');

        return response()->json([
            'status' => true,
            'user' => [
                'id' => $user->id,
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
                'email' => $user->email,
                'phoneNumber' => $user->phoneNumber,
                'alternatePhoneNumber' => $user->alternatePhoneNumber,
                'role' => $user->role,
                'status' => $user->status,
                'facilityId' => $user->facilityId,
                'facility' => $user->facility ? [
                    'id' => $user->facility->facilityId,
                    'facilityName' => $user->facility->facilityName,
                    'facilityCode' => $user->facility->facilityCode,
                ] : null,
                'createdAt' => $user->created_at,
                'updatedAt' => $user->updated_at,
            ],
        ]);
    }

    /**
     * Create a new user (respects role hierarchy)
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = Auth::user();

        $validator = Validator::make($request->all(), [
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phoneNumber' => 'required|string|max:20',
            'alternatePhoneNumber' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'role' => 'required|string',
            'facilityId' => 'required|exists:facilities,id',
            'status' => 'sometimes|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Role-based validation
        if ($currentUser->role === 'SUPER_ADMIN') {
            // Super admin can only create facility_admin role
            if ($request->role !== 'facility_admin') {
                return response()->json([
                    'status' => false,
                    'message' => 'Super admin can only create facility administrators',
                ], 403);
            }
        } elseif ($currentUser->role === 'facility_admin') {
            // Facility admin cannot create SUPER_ADMIN or facility_admin
            if (in_array($request->role, ['SUPER_ADMIN', 'facility_admin'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'You cannot create super admin or facility admin users',
                ], 403);
            }

            // Facility admin can only create users in their own facility
            if ($request->facilityId != $currentUser->facilityId) {
                return response()->json([
                    'status' => false,
                    'message' => 'You can only create users in your own facility',
                ], 403);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to create users',
            ], 403);
        }

        $user = User::create([
            'firstName' => $request->firstName,
            'lastName' => $request->lastName,
            'email' => $request->email,
            'phoneNumber' => $request->phoneNumber,
            'alternatePhoneNumber' => $request->alternatePhoneNumber,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'facilityId' => $request->facilityId,
            'status' => $request->status ?? 'active',
        ]);

        $user->load('facility');

        return response()->json([
            'status' => true,
            'message' => 'User created successfully',
            'user' => [
                'id' => $user->id,
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
                'email' => $user->email,
                'phoneNumber' => $user->phoneNumber,
                'role' => $user->role,
                'facilityId' => $user->facilityId,
                'facility' => $user->facility,
                'status' => $user->status,
            ],
        ], 201);
    }

    /**
     * Update a user (respects role hierarchy)
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $currentUser = Auth::user();

        // Access check
        if ($currentUser->user_role?->roleName  === 'FACILITY_ADMIN' && $user->facilityId !== $currentUser->facilityId) {
            return response()->json([
                'status' => false,
                'message' => 'You can only update users in your own facility',
            ], 403);
        }

        // Cannot update SUPER_ADMIN or facility_admin if you're not SUPER_ADMIN
        if ($currentUser->user_role?->roleName  === 'FACILITY_ADMIN' && in_array($user->user_role?->roleName, ['SUPER_ADMIN', 'FACILITY_ADMIN'])) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot update super admin or facility admin users',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'firstName' => 'sometimes|required|string|max:255',
            'lastName' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:users,email,' . $user->id,
            'phoneNumber' => 'sometimes|required|string|max:20',
            'alternatePhoneNumber' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
            'role' => 'sometimes|required|string',
            'facilityId' => 'sometimes|required|exists:facilities,id',
            'status' => 'sometimes|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Role change validation
        if ($request->has('role')) {
            if ($currentUser->role === 'FACILITY_ADMIN' && in_array($request->role, ['SUPER_ADMIN', 'FACILITY_ADMIN'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'You cannot assign super admin or facility admin roles',
                ], 403);
            }
        }

        // Facility change validation
        if ($request->has('facilityId') && $currentUser->role === 'FACILITY_ADMIN') {
            if ($request->facilityId != $currentUser->facilityId) {
                return response()->json([
                    'status' => false,
                    'message' => 'You cannot transfer users to other facilities',
                ], 403);
            }
        }

        $updateData = $request->only([
            'firstName',
            'lastName',
            'email',
            'phoneNumber',
            'alternatePhoneNumber',
            'role',
            'facilityId',
            'status',
        ]);

        // Only update password if provided
        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);
        $user->load('facility');

        return response()->json([
            'status' => true,
            'message' => 'User updated successfully',
            'user' => [
                'id' => $user->id,
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
                'email' => $user->email,
                'phoneNumber' => $user->phoneNumber,
                'role' => $user->role,
                'facilityId' => $user->facilityId,
                'facility' => $user->facility,
                'status' => $user->status,
            ],
        ]);
    }

    /**
     * Delete a user (respects role hierarchy)
     */
    public function destroy(User $user): JsonResponse
    {
        $currentUser = Auth::user();

        // Don't allow deleting yourself
        if ($user->id === $currentUser->id) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot delete your own account',
            ], 422);
        }

        // Access check
        if ($currentUser->user_role?->roleName === 'FACILITY_ADMIN') {
            // Can only delete users in their facility
            if ($user->facilityId !== $currentUser->facilityId) {
                return response()->json([
                    'status' => false,
                    'message' => 'You can only delete users in your own facility',
                ], 403);
            }

            // Cannot delete SUPER_ADMIN or facility_admin
            if (in_array($user->user_role?->roleName, ['SUPER_ADMIN', 'FACILITY_ADMIN'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'You cannot delete super admin or facility admin users',
                ], 403);
            }
        }

        $user->delete();

        return response()->json([
            'status' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Get available roles based on current user's role
     */
    public function roles(): JsonResponse
    {
        $currentUser = Auth::user();

        if ($currentUser->user_role?->roleName === 'SUPER_ADMIN') {
            // Super admin can only see facility_admin role when creating users
            $roles = Role::where('roleName', 'FACILITY_ADMIN')->get();
        } elseif ($currentUser->user_role?->roleName === 'FACILITY_ADMIN') {
            // Facility admin sees all roles except SUPER_ADMIN and facility_admin
            $roles = Role::whereNotIn('roleName', ['SUPER_ADMIN', 'FACILITY_ADMIN'])->get();
        } else {
            // Other users cannot create users, return empty
            $roles = [];
        }

        return response()->json([
            'status' => true,
            'roles' => $roles,
        ]);
    }

    /**
     * Get facilities for dropdown
     */
    public function facilities(): JsonResponse
    {
        $currentUser = Auth::user();

        if ($currentUser->role === 'SUPER_ADMIN') {
            // Super admin sees all active facilities
            $facilities = Facility::where('status', 'active')
                ->select('facilityId', 'facilityName', 'facilityCode')
                ->get();
        } elseif ($currentUser->role === 'facility_admin') {
            // Facility admin only sees their own facility
            $facilities = Facility::where('facilityId', $currentUser->facilityId)
                ->where('status', 'active')
                ->select('facilityId', 'facilityName', 'facilityCode')
                ->get();
        } else {
            // Other users cannot create users, return empty
            $facilities = [];
        }

        return response()->json([
            'status' => true,
            'facilities' => $facilities,
        ]);
    }
}