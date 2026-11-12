<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the signed-in Bloom endpoints. Used by class name in routes/bloom.php,
 * so it needs no registration in the kernel / bootstrap file.
 *
 * Staff and Bloom tokens are both JWTs signed with the same secret, so this
 * also checks the `typ` claim to make sure a staff token can never act as a
 * Bloom account (and the "bloom" guard already refuses them via lock_subject).
 */
class EnsureBloomAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('bloom');

        try {
            $account = $guard->check() ? $guard->user() : null;
            $typ     = $account ? $guard->payload()->get('typ') : null;
        } catch (\Throwable $e) {
            $account = null;
            $typ     = null;
        }

        if (!$account || $typ !== 'bloom') {
            return response()->json([
                'status'  => false,
                'code'    => 'UNAUTHENTICATED',
                'message' => 'Please sign in again.',
            ], 401);
        }

        if (!$account->isActive()) {
            return response()->json([
                'status'  => false,
                'code'    => 'ACCOUNT_INACTIVE',
                'message' => 'Your account is inactive. Please contact support.',
            ], 403);
        }

        if (!$account->isVerified()) {
            return response()->json([
                'status'  => false,
                'code'    => 'PHONE_NOT_VERIFIED',
                'message' => 'Please verify your phone number first.',
            ], 403);
        }

        Auth::shouldUse('bloom');

        return $next($request);
    }
}
