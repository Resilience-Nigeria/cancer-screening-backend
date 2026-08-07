<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSelfAssessmentRequest;
use App\Models\AwarenessRegistration;
use App\Models\SelfAssessment;
use App\Models\ClientReferral;
use App\Services\RiskClassificationService;
use App\Services\SymptomIdMapper;
use App\Services\FacilityService;
use App\Services\NavigatorService;
use App\Services\BloomClientConversionService;
use App\Events\ClientLinkedToScreeningCenter;
use Illuminate\Http\JsonResponse;

class SelfAssessmentController extends Controller
{
    private const ESCALATE_TO_REFERRAL_TIERS = ['symptomatic_high', 'increased'];

    public function __construct(
        protected RiskClassificationService $riskService,
        protected FacilityService $facilityService,
        protected NavigatorService $navigatorService,
        protected BloomClientConversionService $clientConversion,
    ) {}

 public function store(StoreSelfAssessmentRequest $request, string $registrationId): JsonResponse
    {
        $registration = AwarenessRegistration::with(['facility', 'navigator.user'])
            ->findOrFail($registrationId);

        $validated = $request->validated();
        $age = $validated['age'] ?? null;
        $rawAnswers = $request->input('answers', []);
        $mappedSymptoms = SymptomIdMapper::toBackendIds($rawAnswers['symptoms'] ?? []);

        $classificationInput = array_merge($rawAnswers, [
            'age' => $age,
            'symptoms' => $mappedSymptoms,
        ]);

        $result = $this->riskService->classify($classificationInput, $registration->gender);

        $originFacility = $registration->facility; // matched at registration
        $navigator = $registration->navigator;

        $linkTarget = $originFacility;
        $referralFrom = null;

        // High/increased risk: escalate to nearest Hub/SubHub, skipping Feeders.
        if (in_array($result['riskCategory'], self::ESCALATE_TO_REFERRAL_TIERS, true)) {
            $referralFacility = $this->facilityService->findNearestReferralFacility(
                state: $registration->stateOfResidence,
                lga:   $registration->lgaOfResidence,
                area:  $registration->areaOfResidence,
            );

            if ($referralFacility && $referralFacility->facilityId !== $originFacility?->facilityId) {
                $linkTarget = $referralFacility;
                $referralFrom = $originFacility;
            }
        }

        // Capture linkage for ANY facility match, regardless of risk tier —
        // a Client + ClientReferral now get created whenever someone is
        // matched to a facility, not only on high-risk escalation. This is
        // purely a tracking change: what the client sees below, and whether
        // a notification fires, still depends on risk tier exactly as before.
        if ($linkTarget) {
            $client = $this->clientConversion->convert($registration, $linkTarget);

            if ($client->linkedFacilityId !== $linkTarget->facilityId) {
                $client->update(['linkedFacilityId' => $linkTarget->facilityId]);
            }

            $alreadyReferredHere = ClientReferral::where('clientId', $client->clientId)
                ->where('toFacilityId', $linkTarget->facilityId)
                ->exists();

            if (!$alreadyReferredHere) {
                ClientReferral::create([
                    'clientId'       => $client->clientId,
                    'fromFacilityId' => $referralFrom?->facilityId,
                    'toFacilityId'   => $linkTarget->facilityId,
                    'referralType'   => 'awareness_to_screening',
                    'status'         => 'pending',
                    'referralDate'   => now(),
                    'notes'          => "Auto-linked from Bloom self-assessment (risk: {$result['riskCategory']})",
                ]);
            }



            // Escalation moved the target facility — navigator must follow.
            if ($referralFrom) {
                $navigator = $this->navigatorService->assignNavigator($linkTarget);
                $registration->update(['navigatorId' => $navigator?->id]);
            }
        }

        $assessment = SelfAssessment::create([
            'clientId'                 => $registration->clientId,
            'registrationId'           => $registration->registrationId,
            'answersJson'              => array_merge($rawAnswers, ['age' => $age]),
            'riskCategory'             => $result['riskCategory'],
            'recommendation'           => $result['recommendation'],
            'flaggedReasonsJson'       => $result['flaggedReasons'],
            'suggestedCancerTypesJson' => $result['suggestedCancerTypes'],
            'completedAt'              => now(),
        ]);

        // Client-facing display + notification unchanged — still risk-gated.
        $facility = ($result['riskCategory'] !== 'low') ? $linkTarget : null;

        $navigatorName = $navigator?->user
            ? trim(implode(' ', array_filter([
                $navigator->user->firstName,
                $navigator->user->lastName,
                $navigator->user->otherNames,
              ])))
            : null;

        if ($facility) {
            ClientLinkedToScreeningCenter::dispatch(
                (object) [
                    'fullName'    => $registration->fullName,
                    'email'       => $registration->email,
                    'phoneNumber' => $registration->phoneNumber,
                ],
                $facility,
                $navigator,
            );
        }

        return response()->json([
            'assessmentId'   => (string) $assessment->assessmentId,
            'riskCategory'   => $result['riskCategory'],
            'recommendation' => $result['recommendation'],
            'flaggedReasons' => $result['flaggedReasons'],
            'facility'       => $facility ? [
                'facilityName'       => $facility->facilityName,
                'facilityAddress'    => $facility->facilityAddress,
                'navigatorName'      => $navigatorName ?: $facility->navigatorName,
                'navigatorPhone'     => $navigator?->user?->phoneNumber ?? $facility->navigatorPhone,
                'clinicHoursDisplay' => $facility->formatClinicHours(),
            ] : null,
        ], 201);
    }


    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        $user = $request->user();
        $visibleIds = $user->visibleFacilityIds();
        $search = $request->string('search')->toString();

        $assessments = SelfAssessment::with(['registration', 'client'])
            ->when($visibleIds !== null, function ($q) use ($visibleIds) {
                $q->whereHas('registration', fn ($r) => $r->whereIn('linkedFacilityId', $visibleIds));
            })
            ->when($search, function ($q) use ($search) {
                $q->whereHas('registration', function ($r) use ($search) {
                    $r->where('fullName', 'like', "%{$search}%")
                        ->orWhere('phoneNumber', 'like', "%{$search}%");
                });
            })
            ->latest('completedAt')
            ->paginate(20);

        return response()->json($assessments);
    }

}
