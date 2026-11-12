<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\OverdueResults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverdueResultsController extends Controller
{
    /**
     * GET /screening-samples/overdue?days=7
     * Samples / tests still waiting for a result after `days` days. Admins only; facility-scoped.
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth('api')->user();
        abort_if(!OverdueResults::isAdmin($user), 403, 'Only admins receive overdue-result alerts.');

        $days = max(1, min((int) $request->query('days', OverdueResults::DEFAULT_DAYS), 90));

        return response()->json(OverdueResults::forUser($user, $days));
    }
}
