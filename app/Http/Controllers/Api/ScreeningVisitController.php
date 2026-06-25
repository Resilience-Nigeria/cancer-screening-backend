<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScreeningVisitRequest;
use App\Models\Client;
use App\Models\ScreeningVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ScreeningVisitController extends Controller
{
    // public function index(Client $client): JsonResponse
    // {
    //     $this->authorizeClient($client);

    //     $visits = $client->visits()
    //         ->with([
    //             'cervicalScreening',
    //             'breastScreening',
    //             'colorectalScreening',
    //             'liverScreening',
    //             'prostateScreening',
    //         ])
    //         ->latest('visitDate')
    //         ->get();

    //     return response()->json([
    //         'visits' => $visits,
    //     ]);
    // }


 public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $search = $request->input('search', '');
        $visitType = $request->input('visitType', '');
        $filter = $request->input('filter', '');
        $limit = $request->input('limit', 10);
        $offset = ($page - 1) * $limit;
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        $query = ScreeningVisit::with(['client.facility', 'cervicalScreening', 'breastScreening', 
                              'prostateScreening', 'colorectalScreening', 'liverScreening']);

        // Apply search
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('client', function ($clientQuery) use ($search) {
                    $clientQuery->where('fullName', 'like', "%{$search}%")
                               ->orWhere('phoneNumber', 'like', "%{$search}%")
                               ->orWhere('screeningId', 'like', "%{$search}%");
                })->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Apply visit type filter
        if ($visitType) {
            $query->where('visitType', $visitType);
        }

        // Apply dashboard filters
        if ($filter === 'this_month') {
            $query->whereMonth('visitDate', $currentMonth)
                  ->whereYear('visitDate', $currentYear);
        } elseif ($filter === 'pending_followups') {
            $query->where('visitType', 'follow_up')
                  ->whereDoesntHave('caseOutcome', function ($q) {
                      $q->where('treatmentCompleted', true);
                  });
        }

        $total = $query->count();

        $visits = $query->orderBy('visitDate', 'desc')
                        ->skip($offset)
                        ->take($limit)
                        ->get();

        return response()->json([
            'data' => $visits,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }


    public function indexAll(Request $request): JsonResponse
{
    $facilityId = auth('api')->user()->facilityId;

    $query = ScreeningVisit::with([
        'client',
        'cervicalScreening',
        'breastScreening',
        'colorectalScreening',
        'liverScreening',
        'prostateScreening',
    ])->where('facilityId', $facilityId);

    if ($request->filled('visitType')) {
        $query->where('visitType', $request->visitType);
    }

    if ($request->filled('search')) {
        $search = $request->search;

        $query->where(function ($q) use ($search) {
            $q->where('notes', 'like', "%{$search}%")
              ->orWhereHas('client', function ($clientQuery) use ($search) {
                  $clientQuery->where('fullName', 'like', "%{$search}%")
                              ->orWhere('phoneNumber', 'like', "%{$search}%");
              });
        });
    }

    $visits = $query->latest('visitDate')->paginate(10);

    return response()->json($visits);
}

    public function store(StoreScreeningVisitRequest $request, Client $client): JsonResponse
    {
        $this->authorizeClient($client);

        $visit = ScreeningVisit::create([
            ...$request->validated(),
            'clientId' => $client->clientId,
            'facilityId' => $client->facilityId,
            'createdBy' => auth('api')->id(),
        ]);

        return response()->json([
            'message' => 'Screening visit created successfully',
            'visit' => $visit,
        ], 201);
    }

    public function show(ScreeningVisit $visit): JsonResponse
    {
        $this->authorizeVisit($visit);

        $visit->load([
            'client',
            'cervicalScreening',
            'breastScreening',
            'colorectalScreening',
            'liverScreening',
            'prostateScreening',
        ]);

        return response()->json([
            'visit' => $visit,
        ]);
    }

    protected function authorizeClient(Client $client): void
    {
        $user = auth('api')->user();

        if (!$user->isSuperAdmin() || !$user->isPartner() && $client->facility_id !== $user->facility_id) {
            abort(403, 'You cannot access this client');
        }
    }

    protected function authorizeVisit(ScreeningVisit $visit): void
    {
        $user = auth('api')->user();

        if (!$user->isSuperAdmin() || !$user->isPartner() && $visit->facilityId !== $user->facilityId) {
            abort(403, 'You cannot access this visit');
        }
    }
}