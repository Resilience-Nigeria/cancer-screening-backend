<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Facility;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Get all users with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['facility']);

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

        // Filter by facility
        if ($request->has('facility') && $request->facility !== 'all') {
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
                'role' => $user->role,
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

        // Calculate stats
        $stats = [
            'total' => User::count(),
            'active' => User::where('status', 'active')->count(),
            'inactive' => User::where('status', 'inactive')->count(),
            'byRole' => User::select('role', \DB::raw('count(*) as total'))
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
                    'id' => $user->facility->id,
                    'facilityName' => $user->facility->facilityName,
                    'facilityCode' => $user->facility->facilityCode,
                ] : null,
                'createdAt' => $user->created_at,
                'updatedAt' => $user->updated_at,
            ],
        ]);
    }

    /**
     * Create a new user
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'firstName' => 'required|string|max:255',
            'lastName' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phoneNumber' => 'required|string|max:20',
            'alternatePhoneNumber' => 'nullable|string|max:20',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,super_admin,doctor,nurse,data_clerk',
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
     * Update a user
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'firstName' => 'sometimes|required|string|max:255',
            'lastName' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255|unique:users,email,' . $user->id,
            'phoneNumber' => 'sometimes|required|string|max:20',
            'alternatePhoneNumber' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
            'role' => 'sometimes|required|in:admin,super_admin,doctor,nurse,data_clerk',
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
     * Delete a user
     */
    public function destroy(User $user): JsonResponse
    {
        // Don't allow deleting yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot delete your own account',
            ], 422);
        }

        // Check if user has data (optional - adjust based on your needs)
        // if ($user->screeningVisits()->count() > 0) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'Cannot delete user with existing screening data',
        //     ], 422);
        // }

        $user->delete();

        return response()->json([
            'status' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Get available roles
     */
    public function roles(): JsonResponse
    {
        $roles = [
            [
                'value' => 'admin',
                'label' => 'Administrator',
                'description' => 'Full system access and user management'
            ],
            [
                'value' => 'super_admin',
                'label' => 'Super Administrator',
                'description' => 'Highest level access with all permissions'
            ],
            [
                'value' => 'doctor',
                'label' => 'Doctor',
                'description' => 'Medical professional with screening access'
            ],
            [
                'value' => 'nurse',
                'label' => 'Nurse',
                'description' => 'Nursing staff with screening access'
            ],
            [
                'value' => 'data_clerk',
                'label' => 'Data Clerk',
                'description' => 'Data entry and record management'
            ],
        ];

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
        $facilities = Facility::where('status', 'active')
            ->select('facilityId', 'facilityName', 'facilityCode')
            ->get();

        return response()->json([
            'status' => true,
            'facilities' => $facilities,
        ]);
    }
}