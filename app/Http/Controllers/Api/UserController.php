<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Facility;
use App\Models\Organization;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Services\BrevoMailService;

class UserController extends Controller
{
    /**
     * Generate a secure random password
     */
    private function generatePassword(): string
    {
        // Generate a random password with uppercase, lowercase, numbers
        $password = Str::upper(Str::random(2)) . 
                   Str::lower(Str::random(4)) . 
                   rand(100, 999) . 
                   Str::random(1);
        
        return $password;
    }

    /**
     * Send welcome email with credentials
     */
    // private function sendWelcomeEmail(User $user, string $plainPassword): void
    // {
    //     try {
    //         Mail::send('emails.welcome', [
    //             'user' => $user,
    //             'password' => $plainPassword,
    //             'loginUrl' => config('app.frontend_url') . '/',
    //         ], function ($message) use ($user) {
    //             $message->to($user->email, $user->firstName . ' ' . $user->lastName)
    //                     ->subject('Welcome to NCSR - Your Account Details');
    //         });
    //     } catch (\Exception $e) {
    //         // Log the error but don't fail the user creation
    //         \Log::error('Failed to send welcome email: ' . $e->getMessage());
    //     }
    // }


   

private function sendWelcomeEmail(User $user, string $plainPassword): void
{
    try {
        app(BrevoMailService::class)
            ->sendWelcomeEmail($user, $plainPassword);
    } catch (\Exception $e) {
        \Log::error('Failed to send welcome email: ' . $e->getMessage());
    }
}

    /**
     * Get all users with filters (respects role hierarchy and hides super admins)
     */
    public function index(Request $request): JsonResponse
    {
        $currentUser = Auth::user();
        $query = User::with(['facility', 'user_role']);

        // Get current user's role name
        $currentUserRole = $currentUser->user_role?->roleName;

        // Filter users based on current user's role
        if ($currentUserRole === 'NICRAT_SUPER_ADMIN') {
            $query->whereHas('user_role', function($q) {
                $q->whereIn('roleName', ['NICRAT_ADMIN', 'NAVIGATOR', 'NURSE']);
            });
        } 
        elseif ($currentUserRole === 'NAVIGATOR') {
            $query->where('facilityId', $currentUser->facilityId)
                  ->whereHas('user_role', function($q) {
                      $q->where('roleName', 'NURSE');
                  });
        }
        elseif ($currentUserRole === 'NICRAT_ADMIN') {
            $query->whereHas('user_role', function($q) {
                $q->whereIn('roleName', ['NICRAT_ADMIN', 'NAVIGATOR', 'NURSE']);
            });
        }
        else {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to view users',
            ], 403);
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

        // Filter by facility
        if ($request->has('facility') && $request->facility !== 'all') {
            if (in_array($currentUserRole, ['NICRAT_SUPER_ADMIN', 'NICRAT_ADMIN'])) {
                $query->where('facilityId', $request->facility);
            }
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
                'role' => $user->user_role?->roleName ?? $user->role,
                'status' => $user->status,
                'facilityId' => $user->facilityId,
                'facility' => $user->facility ? [
                    'id' => $user->facility->id,
                    'facilityId' => $user->facility->facilityId,
                    'facilityName' => $user->facility->facilityName,
                    'facilityCode' => $user->facility->facilityCode,
                ] : null,
                'createdAt' => $user->created_at,
                'updatedAt' => $user->updated_at,
            ];
        });

        // Calculate stats
        if ($currentUserRole === 'NICRAT_SUPER_ADMIN') {
            $statsQuery = User::whereHas('user_role', function($q) {
                $q->whereIn('roleName', ['NICRAT_ADMIN', 'NAVIGATOR', 'NURSE']);
            });
        } elseif ($currentUserRole === 'NAVIGATOR') {
            $statsQuery = User::where('facilityId', $currentUser->facilityId)
                              ->whereHas('user_role', function($q) {
                                  $q->where('roleName', 'NURSE');
                              });
        } elseif ($currentUserRole === 'NICRAT_ADMIN') {
            $statsQuery = User::whereHas('user_role', function($q) {
                $q->whereIn('roleName', ['NICRAT_ADMIN', 'NAVIGATOR', 'NURSE']);
            });
        } else {
            $statsQuery = User::where('id', 0);
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
        $currentUserRole = $currentUser->user_role?->roleName;

        // Check access based on role
        if ($currentUserRole === 'NICRAT_SUPER_ADMIN') {
            if ($user->user_role?->roleName === 'NICRAT_SUPER_ADMIN' && $user->id !== $currentUser->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access',
                ], 403);
            }
        } elseif ($currentUserRole === 'NAVIGATOR') {
            if ($user->facilityId !== $currentUser->facilityId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access',
                ], 403);
            }
        } elseif ($currentUserRole === 'NICRAT_ADMIN') {
            if ($user->user_role?->roleName === 'NICRAT_SUPER_ADMIN') {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized access',
                ], 403);
            }
        } else {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized access',
            ], 403);
        }

        $user->load(['facility', 'user_role']);

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
                'user_role' => $user->user_role,
                'status' => $user->status,
                'facilityId' => $user->facilityId,
                'facility' => $user->facility ? [
                    'id' => $user->facility->id,
                    'facilityId' => $user->facility->facilityId,
                    'facilityName' => $user->facility->facilityName,
                    'facilityCode' => $user->facility->facilityCode,
                ] : null,
                'createdAt' => $user->created_at,
                'updatedAt' => $user->updated_at,
            ],
        ]);
    }

    /**
     * Create a new user with auto-generated password
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
            // Password field removed - will be auto-generated
            'role' => 'required|string|exists:roles,roleId',
            'facilityId' => 'nullable|exists:facilities,facilityId',
            'status' => 'sometimes|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Get the role being assigned
        $roleBeingAssigned = Role::where('roleId', $request->role)->first();
        
        if (!$roleBeingAssigned) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid role specified',
            ], 422);
        }

        // Get current user's role name
        $currentUserRole = $currentUser->user_role?->roleName;

        // Role-based validation
        if ($currentUserRole === 'NICRAT_SUPER_ADMIN') {
            $allowedRoles = ['NICRAT_ADMIN', 'NAVIGATOR', 'PARTNER'];
            
            if (!in_array($roleBeingAssigned->roleName, $allowedRoles)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Super admin can only create NICRAT Staff, Partners and Hospital Administrators',
                ], 403);
            }

            if ($roleBeingAssigned->roleName === 'NAVIGATOR' && !$request->facilityId) {
                return response()->json([
                    'status' => false,
                    'message' => 'Hospital administrators must be assigned to a facility',
                ], 422);
            }
        } 
        elseif ($currentUserRole === 'NAVIGATOR') {
            if ($roleBeingAssigned->roleName !== 'NURSE') {
                return response()->json([
                    'status' => false,
                    'message' => 'Hospital administrators can only create Data Clerks',
                ], 403);
            }

            if ($request->facilityId != $currentUser->facilityId) {
                return response()->json([
                    'status' => false,
                    'message' => 'You can only create users in your own facility',
                ], 403);
            }

            $request->merge(['facilityId' => $currentUser->facilityId]);
        } 
        else {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to create users',
            ], 403);
        }

        // Generate random password
        $plainPassword = $this->generatePassword();

        // Create the user
        $user = User::create([
            'firstName' => $request->firstName,
            'lastName' => $request->lastName,
            'email' => $request->email,
            'phoneNumber' => $request->phoneNumber,
            'alternatePhoneNumber' => $request->alternatePhoneNumber,
            'password' => Hash::make($plainPassword),
            'role' => $request->role,
            'facilityId' => $request->facilityId,
            'status' => $request->status ?? 'active',
        ]);

        // Load relationships
        $user->load(['facility', 'user_role']);

        // Send welcome email with credentials
        $this->sendWelcomeEmail($user, $plainPassword);

        return response()->json([
            'status' => true,
            'message' => 'User created successfully. Login credentials have been sent to their email.',
            'user' => [
                'id' => $user->id,
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
                'email' => $user->email,
                'phoneNumber' => $user->phoneNumber,
                'alternatePhoneNumber' => $user->alternatePhoneNumber,
                'role' => $user->role,
                'user_role' => $user->user_role,
                'facilityId' => $user->facilityId,
                'facility' => $user->facility,
                'status' => $user->status,
            ],
        ], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $currentUser = Auth::user();
        $user = User::with(['facility', 'user_role'])->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'firstName' => 'sometimes|string|max:255',
            'lastName' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $id,
            'phoneNumber' => 'sometimes|string|max:20',
            'alternatePhoneNumber' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
            'role' => 'sometimes|string|exists:roles,roleId',
            'facilityId' => 'sometimes|exists:facilities,facilityId',
            'status' => 'sometimes|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $currentUserRole = $currentUser->user_role?->roleName;

        // Role-based update validation
        if ($currentUserRole === 'NICRAT_SUPER_ADMIN') {
            if ($user->user_role?->roleName === 'NICRAT_SUPER_ADMIN' && $user->id !== $currentUser->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'You cannot edit other super administrators',
                ], 403);
            }
        } 
        elseif ($currentUserRole === 'NAVIGATOR') {
            if ($user->facilityId !== $currentUser->facilityId) {
                return response()->json([
                    'status' => false,
                    'message' => 'You can only edit users in your own facility',
                ], 403);
            }

            if ($request->has('role')) {
                $newRole = Role::where('roleId', $request->role)->first();
                if (in_array($newRole?->roleName, ['NICRAT_SUPER_ADMIN', 'NAVIGATOR', 'NICRAT_ADMIN'])) {
                    return response()->json([
                        'status' => false,
                        'message' => 'You cannot assign super admin, NICRAT staff, or hospital admin roles',
                    ], 403);
                }
            }

            if ($request->has('facilityId') && $request->facilityId != $currentUser->facilityId) {
                return response()->json([
                    'status' => false,
                    'message' => 'You cannot assign users to other facilities',
                ], 403);
            }
        } 
        else {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to edit users',
            ], 403);
        }

        // Update user
        $user->update([
            'firstName' => $request->firstName ?? $user->firstName,
            'lastName' => $request->lastName ?? $user->lastName,
            'email' => $request->email ?? $user->email,
            'phoneNumber' => $request->phoneNumber ?? $user->phoneNumber,
            'alternatePhoneNumber' => $request->alternatePhoneNumber ?? $user->alternatePhoneNumber,
            'role' => $request->role ?? $user->role,
            'facilityId' => $request->facilityId ?? $user->facilityId,
            'status' => $request->status ?? $user->status,
        ]);

        // Update password if provided
        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        $user->load(['facility', 'user_role']);

        return response()->json([
            'status' => true,
            'message' => 'User updated successfully',
            'user' => [
                'id' => $user->id,
                'firstName' => $user->firstName,
                'lastName' => $user->lastName,
                'email' => $user->email,
                'phoneNumber' => $user->phoneNumber,
                'alternatePhoneNumber' => $user->alternatePhoneNumber,
                'role' => $user->role,
                'user_role' => $user->user_role,
                'facilityId' => $user->facilityId,
                'facility' => $user->facility,
                'status' => $user->status,
            ],
        ]);
    }

    public function destroy($id): JsonResponse
    {
        $currentUser = Auth::user();
        $user = User::with('user_role')->findOrFail($id);

        $currentUserRole = $currentUser->user_role?->roleName;

        // Role-based delete validation
        if ($currentUserRole === 'NICRAT_SUPER_ADMIN') {
            if ($user->user_role?->roleName === 'NICRAT_SUPER_ADMIN') {
                return response()->json([
                    'status' => false,
                    'message' => 'You cannot delete super administrators',
                ], 403);
            }
        } 
        elseif ($currentUserRole === 'NAVIGATOR') {
            if ($user->facilityId !== $currentUser->facilityId) {
                return response()->json([
                    'status' => false,
                    'message' => 'You can only delete users in your own facility',
                ], 403);
            }

            if (in_array($user->user_role?->roleName, ['NAVIGATOR', 'NICRAT_ADMIN'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'You cannot delete hospital administrators or NICRAT staff',
                ], 403);
            }
        } 
        else {
            return response()->json([
                'status' => false,
                'message' => 'You do not have permission to delete users',
            ], 403);
            }

        // Prevent self-deletion
        if ($user->id === $currentUser->id) {
            return response()->json([
                'status' => false,
                'message' => 'You cannot delete your own account',
            ], 403);
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
        $currentUserRole = $currentUser->user_role?->roleName;

        if ($currentUserRole === 'NICRAT_SUPER_ADMIN') {
            $roles = Role::whereIn('roleName', ['NICRAT_ADMIN', 'NAVIGATOR', 'PARTNER'])->get();
        } elseif ($currentUserRole === 'NAVIGATOR') {
            $roles = Role::where('roleName', 'NURSE')->get();
        } else {
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
        $currentUserRole = $currentUser->user_role?->roleName;

        if ($currentUserRole === 'NICRAT_SUPER_ADMIN') {
            $facilities = Facility::where('status', 'active')
                ->select('facilityId', 'facilityName', 'facilityCode')
                ->get();
        } elseif ($currentUserRole === 'NAVIGATOR') {
            $facilities = Facility::where('facilityId', $currentUser->facilityId)
                ->where('status', 'active')
                ->select('facilityId', 'facilityName', 'facilityCode')
                ->get();
        } else {
            $facilities = [];
        }

        return response()->json([
            'status' => true,
            'facilities' => $facilities,
        ]);
    }


    /**
 * Get all organizations for partner role
 */
public function getOrganizations()
{
    try {
        $organizations = Organization::where('status', true)
            ->select('id', 'organizationName', 'organizationCode', 'contactEmail')
            ->orderBy('organizationName')
            ->get();

        return response()->json([
            'status' => true,
            'organizations' => $organizations,
            'message' => 'Organizations fetched successfully'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to fetch organizations'
        ], 500);
    }
}
}