<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Models\RefreshToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Handle user login with detailed response
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        // Find user by email or phone number
        $user = User::with(['facility'])
            ->where('email', $credentials['email'])
            ->orWhere('phoneNumber', $credentials['email']) // Allow phone number login
            ->first();

        if (!$user) {
            return response()->json([
                'status'  => false,
                'code'    => 'USER_NOT_FOUND',
                'message' => 'No account found with this email or phone number',
            ], 404);
        }

        // Check if user is active
        if (isset($user->status) && $user->status !== 'active') {
            return response()->json([
                'status'  => false,
                'code'    => 'ACCOUNT_INACTIVE',
                'message' => 'Your account is inactive. Please contact administrator.',
            ], 403);
        }

        // Attempt authentication with the actual email
        if (!$accessToken = Auth::guard('api')->attempt([
            'email'    => $user->email,
            'password' => $credentials['password'],
        ])) {
            return response()->json([
                'status'  => false,
                'code'    => 'INVALID_CREDENTIALS',
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Get authenticated user with relationships
        $user = Auth::guard('api')->user()->load('facility');

        // Generate refresh token
        $refreshToken = Str::random(80);

        // Delete old expired refresh tokens for this user
        RefreshToken::where('user_id', $user->id)
            ->where('expires_at', '<', now())
            ->delete();

        // Store new refresh token in database
        RefreshToken::create([
            'user_id'    => $user->id,
            'token'      => hash('sha256', $refreshToken), // Store hashed version
            'expires_at' => now()->addDays(14),
        ]);

        // Build user response matching your Facility structure
        $userData = [
            'id'                     => $user->id,
            'facilityId'             => $user->facilityId,
            'firstName'              => $user->firstName ?? '',
            'lastName'               => $user->lastName ?? '',
            'email'                  => $user->email ?? '',
            'phoneNumber'            => $user->phoneNumber ?? null,
            'alternatePhoneNumber'   => $user->alternatePhoneNumber ?? null,
            'role'                   => $user->user_role?->roleName ?? null,
            // Computed server-side from the role's configured
            // dataScopeType — the frontend should read these directly
            // rather than re-deriving access level from role name
            // strings, so changing a role's scope via the admin page
            // takes effect immediately everywhere without a frontend
            // redeploy.
            'hasNationalAccess'      => $user->hasNationalAccess(),
            'dataScopeType'          => $user->dataScopeType(),
        ];

        // Add facility data if it exists
        if ($user->facility) {
            $userData['facility'] = [
                'id'              => $user->facility->id,
                'facilityName'    => $user->facility->facilityName ?? '',
                'facilityCode'    => $user->facility->facilityCode ?? '',
                'facilityState'   => $user->facility->facilityState ?? null,
                'facilityLga'     => $user->facility->facilityLga ?? null,
                'facilityAddress' => $user->facility->facilityAddress ?? null,
                'facilityLevel'   => $user->facility->facilityLevel ?? null,
                'stagesSupported' => $user->facility->stagesSupported ?? [],
            ];
        } else {
            $userData['facility'] = null;
        }

        // Add status if it exists in your User model
        if (isset($user->status)) {
            $userData['status'] = $user->status;
        }

        // Build response
        return response()->json([
            'status'       => true,
            'message'      => 'Logged in successfully',
            'access_token' => $accessToken,
            'token_type'   => 'bearer',
            'expires_in'   => Auth::guard('api')->factory()->getTTL() * 60,
            'user'         => $userData,
        ])
        ->cookie('access_token',  $accessToken,  60 * 24 * 2,   null, null, true, true, false, 'lax')
        ->cookie('refresh_token', $refreshToken, 60 * 24 * 14,  null, null, true, true, false, 'lax');
    }

    /**
     * Get the authenticated user
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        $user = Auth::guard('api')->user()?->load('facility');

        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => 'User not authenticated',
            ], 401);
        }

        // Build user response
        $userData = [
            'id'                     => $user->id,
            'facilityId'             => $user->facilityId,
            'firstName'              => $user->firstName ?? '',
            'lastName'               => $user->lastName ?? '',
            'email'                  => $user->email ?? '',
            'phoneNumber'            => $user->phoneNumber ?? null,
            'alternatePhoneNumber'   => $user->alternatePhoneNumber ?? null,
            'role'                   => $user->user_role?->roleName ?? null,
            'hasNationalAccess'      => $user->hasNationalAccess(),
            'dataScopeType'          => $user->dataScopeType(),
        ];

        // Add facility data if it exists
        if ($user->facility) {
            $userData['facility'] = [
                'id'              => $user->facility->id,
                'facilityName'    => $user->facility->facilityName ?? '',
                'facilityCode'    => $user->facility->facilityCode ?? '',
                'facilityState'   => $user->facility->facilityState ?? null,
                'facilityLga'     => $user->facility->facilityLga ?? null,
                'facilityAddress' => $user->facility->facilityAddress ?? null,
                'facilityLevel'   => $user->facility->facilityLevel ?? null,
                'stagesSupported' => $user->facility->stagesSupported ?? [],
            ];
        } else {
            $userData['facility'] = null;
        }

        if (isset($user->status)) {
            $userData['status'] = $user->status;
        }

        return response()->json([
            'status' => true,
            'user'   => $userData,
        ]);
    }

    /**
     * Refresh the access token using refresh token
     *
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     */
    public function refresh(\Illuminate\Http\Request $request): JsonResponse
    {
        $refreshToken = $request->cookie('refresh_token');

        if (!$refreshToken) {
            return response()->json([
                'status'  => false,
                'code'    => 'REFRESH_TOKEN_MISSING',
                'message' => 'Refresh token not provided',
            ], 401);
        }

        // Find the refresh token
        $tokenRecord = RefreshToken::where('token', hash('sha256', $refreshToken))
            ->where('expires_at', '>', now())
            ->first();

        if (!$tokenRecord) {
            return response()->json([
                'status'  => false,
                'code'    => 'REFRESH_TOKEN_INVALID',
                'message' => 'Invalid or expired refresh token',
            ], 401);
        }

        // Get the user
        $user = User::with(['facility'])->find($tokenRecord->user_id);

        if (!$user) {
            return response()->json([
                'status'  => false,
                'message' => 'User not found',
            ], 404);
        }

        // Check if user is still active
        if (isset($user->status) && $user->status !== 'active') {
            return response()->json([
                'status'  => false,
                'code'    => 'ACCOUNT_INACTIVE',
                'message' => 'Your account is inactive',
            ], 403);
        }

        // Generate new access token
        $accessToken = Auth::guard('api')->login($user);

        return response()->json([
            'status'       => true,
            'message'      => 'Token refreshed successfully',
            'access_token' => $accessToken,
            'token_type'   => 'bearer',
            'expires_in'   => Auth::guard('api')->factory()->getTTL() * 60,
        ])
        ->cookie('access_token', $accessToken, 60 * 24 * 2, null, null, true, true, false, 'lax');
    }

    /**
     * Log the user out (Invalidate the token)
     *
     * @param \Illuminate\Http\Request $request
     * @return JsonResponse
     */
    public function logout(\Illuminate\Http\Request $request): JsonResponse
    {
        // Get refresh token from cookie
        $refreshToken = $request->cookie('refresh_token');

        // Delete refresh token from database
        if ($refreshToken) {
            RefreshToken::where('token', hash('sha256', $refreshToken))->delete();
        }

        // Invalidate JWT token
        Auth::guard('api')->logout();

        return response()->json([
            'status'  => true,
            'message' => 'Logged out successfully',
        ])
        ->cookie('access_token', '', -1)
        ->cookie('refresh_token', '', -1);
    }

    /**
     * Legacy method for backward compatibility
     * Can be removed if not used elsewhere
     *
     * @param string $token
     * @return JsonResponse
     */
    protected function respondWithToken(string $token): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => Auth::guard('api')->factory()->getTTL() * 60,
            'user'         => Auth::guard('api')->user()?->load('facility'),
        ]);
    }
}