<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Replays the stored response when the same `Idempotency-Key` is sent again by the same user.
 * Stops a queued offline write that timed out (but reached the server) from creating a duplicate
 * client / visit / screening when the app retries it.
 *
 * Register in app/Http/Kernel.php (or bootstrap/app.php) as 'idempotent' and add it to the
 * POST/PATCH routes used by the clinical screening page:
 *   /clients, /clients/{id}/visits, /clients/{id}/risk-profile,
 *   /visits/{id}/{type}-screening, /visits/{id}/examination, /visits/{id}/outcome
 */
class IdempotencyMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('Idempotency-Key');
        if (!$key || !in_array($request->method(), ['POST', 'PATCH', 'PUT'], true)) {
            return $next($request);
        }

        $userId = auth('api')->id() ?? 'guest';
        $cacheKey = 'idem:' . $userId . ':' . sha1($key);

        if ($cached = Cache::get($cacheKey)) {
            return response($cached['body'], $cached['status'])
                ->header('Content-Type', $cached['type'])
                ->header('Idempotent-Replay', 'true');
        }

        $response = $next($request);

        if ($response->getStatusCode() < 500) {
            Cache::put($cacheKey, [
                'body'   => $response->getContent(),
                'status' => $response->getStatusCode(),
                'type'   => $response->headers->get('Content-Type', 'application/json'),
            ], now()->addDay());
        }

        return $response;
    }
}
