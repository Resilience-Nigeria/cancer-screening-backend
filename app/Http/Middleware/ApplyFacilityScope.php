<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyFacilityScope
{
    /**
     * Handle an incoming request.
     *
     * Apply facility scope for facility-level users (NAVIGATOR, NURSE)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        // If user has facility-level access, store facility_id in request
        if ($user->hasFacilityAccess() && $user->facilityId) {
            $request->merge(['scoped_facility_id' => $user->facilityId]);
        }

        return $next($request);
    }
}