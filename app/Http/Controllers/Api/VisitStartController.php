<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScreeningVisitRequest;
use App\Models\Client;
use App\Models\ScreeningVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * POST /clients/{client}/visits — replaces the route to ScreeningVisitController@store.
 *
 * Stops duplicate encounters (simulation report §8): when a session times out and the nurse signs in again
 * and reopens the client, "start screening" continues today's unfinished visit instead of creating a new one.
 * A visit counts as unfinished until its overall outcome is recorded.
 * Send { forceNew: true } to really start a second visit on the same day.
 */
class VisitStartController extends Controller
{
    public function store(Request $request, string $client): JsonResponse
    {
        $c = Client::where('clientId', $client)->first() ?? Client::findOrFail($client);
        $user = auth('api')->user();
        if (method_exists($user, 'canAccessFacility') && !$user->canAccessFacility($c->facilityId)) {
            abort(403, 'You cannot access this client');
        }

        if (!$request->boolean('forceNew')) {
            $open = ScreeningVisit::where('clientId', $c->clientId)
                ->whereNull('overallOutcome')
                ->where(fn ($q) => $q->whereDate('visitDate', today())->orWhereDate('created_at', today()))
                ->orderByDesc('visitId')
                ->first();
            if ($open) {
                return response()->json([
                    'message' => 'This client already has an unfinished visit today — continuing it.',
                    'visit' => $open,
                    'resumed' => true,
                ], 200);
            }
        }

        // Normal path: exactly what ScreeningVisitController@store did before.
        return app(ScreeningVisitController::class)->store(app(StoreScreeningVisitRequest::class), $c);
    }
}
