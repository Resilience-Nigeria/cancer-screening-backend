<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScreeningVisitRequest;
use App\Http\Requests\StoreVisitExaminationRequest;
use App\Http\Requests\StoreOutcomeClassificationRequest;
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
            'examination',
        ]);

        return response()->json([
            'visit' => $visit,
        ]);
    }

    /**
     * Stage 2, Section E — physical examination (vitals + general exam).
     * One examination per visit; resubmitting updates the same record.
     */
    public function storeExamination(StoreVisitExaminationRequest $request, ScreeningVisit $visit): JsonResponse
    {
        $this->authorizeVisit($visit);

        $data = $request->validated();

        // Auto-calculate BMI server-side if height/weight given but BMI wasn't.
        if (empty($data['bmi']) && !empty($data['heightCm']) && !empty($data['weightKg'])) {
            $heightM = $data['heightCm'] / 100;
            if ($heightM > 0) {
                $data['bmi'] = round($data['weightKg'] / ($heightM * $heightM), 1);
            }
        }

        $examination = \App\Models\VisitExamination::updateOrCreate(
            ['visitId' => $visit->visitId],
            [
                ...$data,
                'visitId' => $visit->visitId,
                'examinedBy' => auth('api')->id(),
                'examinedAt' => now(),
            ]
        );

        return response()->json([
            'message' => 'Physical examination saved successfully',
            'examination' => $examination,
        ], 201);
    }

    /**
     * Stage 2, Section G — overall visit-level outcome classification.
     * Separate from each cancer type's own screeningResult field.
     */
    public function classifyOutcome(
        StoreOutcomeClassificationRequest $request,
        ScreeningVisit $visit,
        \App\Services\ReferralRoutingService $referralRouting,
        \App\Services\ReferralService $referralService,
    ): JsonResponse {
        $this->authorizeVisit($visit);

        $visit->update([
            ...$request->validated(),
            'outcomeClassifiedBy' => auth('api')->id(),
            'outcomeClassifiedAt' => now(),
        ]);

        $referral = null;
        $isSelfReferral = false;

        // Route to Stage 3 (confirmation/diagnostic workup) when the
        // outcome warrants it. Resolution follows the facility hierarchy
        // (parentFacilityId) and each facility's configured
        // stagesSupported — not a hardcoded tier assumption — so if the
        // Stage 2 facility itself is Stage 3-capable, the client simply
        // continues there instead of being sent elsewhere.
        if (in_array($visit->overallOutcome, ['suspicious', 'urgent_referral'], true)) {
            $client = $visit->client;
            $fromFacility = $visit->facility;

            if ($client && $fromFacility) {
                $resolution = $referralRouting->resolveForStage($fromFacility, 'stage3');
                $toFacility = $resolution['facility'];
                $isSelfReferral = $resolution['isSelfReferral'];

                if ($toFacility) {
                    $urgencyNote = $visit->overallOutcome === 'urgent_referral'
                        ? 'URGENT — same-day referral required.'
                        : 'Referred following a suspicious Stage 2 screening outcome.';

                    if ($isSelfReferral) {
                        $urgencyNote .= ' This facility is Stage 3-capable — client continues here, no physical transfer needed.';
                    }

                    $referral = $referralService->referToMainHub(
                        $client,
                        $fromFacility,
                        $toFacility,
                        trim($urgencyNote . ' ' . ($visit->outcomeNotes ?? '')),
                    );
                    $referral->load('toFacility', 'fromFacility');
                }
            }
        }

        return response()->json([
            'message' => 'Screening outcome recorded successfully',
            'visit' => $visit,
            'isSelfReferral' => $isSelfReferral,
            'referral' => $referral,
        ]);
    }

    protected function authorizeClient(Client $client): void
    {
        $user = auth('api')->user();

        if (!$user->canAccessFacility($client->facility_id)) {
            abort(403, 'You cannot access this client');
        }
    }

    protected function authorizeVisit(ScreeningVisit $visit): void
    {
        $user = auth('api')->user();

        if (!$user->canAccessFacility($visit->facilityId)) {
            abort(403, 'You cannot access this visit');
        }
    }
}