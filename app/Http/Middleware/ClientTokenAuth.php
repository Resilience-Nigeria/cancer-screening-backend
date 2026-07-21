<?php

namespace App\Http\Middleware;

use App\Models\ClientLoginToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClientTokenAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $tokenString = $request->bearerToken();

        if (!$tokenString) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $token = ClientLoginToken::where('token', $tokenString)->first();

        if (!$token || $token->isExpired()) {
            return response()->json(['message' => 'Session expired. Please log in again.'], 401);
        }

        $client = $token->client;

        if (!$client) {
            return response()->json(['message' => 'Client record not found.'], 404);
        }

        $token->update(['lastUsedAt' => now()]);

        $request->attributes->set('authenticatedClient', $client);

        return $next($request);
    }
}
