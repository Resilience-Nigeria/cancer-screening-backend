<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IndigencySupportController extends Controller
{
    /**
     * Clients classified as Lower socio-economic status (NICRAT model)
     * — the population most likely to need financial or support
     * assistance. Filterable to those with a confirmed cancer diagnosis
     * or an active treatment plan, since those are the most actionable
     * for outreach.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $visibleIds = $user->visibleFacilityIds();

        $query = Client::with(['latestRiskProfile', 'facility', 'outcome', 'treatmentPlans' => function ($q) {
            $q->where('status', 'active')->latest('treatmentPlanId')->limit(1);
        }])
            ->whereHas('latestRiskProfile', function ($q) {
                $q->where('socioeconomicClass', 'lower');
            });

        if ($visibleIds !== null) {
            $query->whereIn('facilityId', $visibleIds);
        } elseif ($request->has('facilityId') && $request->facilityId !== 'all') {
            $query->where('facilityId', $request->facilityId);
        }

        if ($request->filled('cancerRiskCategory')) {
            $query->whereHas('latestRiskProfile', function ($q) use ($request) {
                $q->where('cancerRiskCategory', $request->cancerRiskCategory);
            });
        }

        if ($request->boolean('confirmedCancerOnly')) {
            $query->whereHas('outcome', function ($q) {
                $q->where('cancerConfirmed', 'yes');
            });
        }

        if ($request->boolean('activeTreatmentOnly')) {
            $query->whereHas('treatmentPlans', function ($q) {
                $q->where('status', 'active');
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('fullName', 'like', "%{$search}%")
                    ->orWhere('phoneNumber', 'like', "%{$search}%")
                    ->orWhere('clientId', 'like', "%{$search}%");
            });
        }

        $clients = $query->orderByDesc('clientId')->paginate(20);

        return response()->json($clients);
    }
}
