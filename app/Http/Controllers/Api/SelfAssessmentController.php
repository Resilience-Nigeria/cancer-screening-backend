<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSelfAssessmentRequest;
use App\Models\AwarenessRegistration;
use App\Models\SelfAssessment;
use App\Services\RiskClassificationService;
use App\Events\ClientLinkedToScreeningCenter;



use App\Services\SymptomIdMapper;
use Illuminate\Http\JsonResponse;

class SelfAssessmentController extends Controller
{
    public function __construct(protected RiskClassificationService $riskService) {}

   public function store(StoreSelfAssessmentRequest $request, string $registrationId): JsonResponse
{
    $registration = AwarenessRegistration::with(['facility', 'navigator.user'])
        ->findOrFail($registrationId);

    $validated = $request->validated();
    $age = $validated['age'] ?? null;

    // validated() can drop 'answers' entirely when nested dot-rules don't
    // match the submitted keys (see note above) — read raw input as a
    // fallback so a genuinely-empty answers object doesn't crash here.
    $rawAnswers = $request->input('answers', []);

    $mappedSymptoms = SymptomIdMapper::toBackendIds($rawAnswers['symptoms'] ?? []);

    // classify() reads age off $answers itself, not as a sibling arg —
    // merge it in rather than passing separately.
    $classificationInput = array_merge($rawAnswers, [
        'age' => $age,
        'symptoms' => $mappedSymptoms,
    ]);

    $result = $this->riskService->classify($classificationInput, $registration->gender);

    $assessment = SelfAssessment::create([
        'registrationId'           => $registration->registrationId,
        'answersJson'              => array_merge($rawAnswers, ['age' => $age]),
        'riskCategory'             => $result['riskCategory'],
        'recommendation'           => $result['recommendation'],
        'flaggedReasonsJson'       => $result['flaggedReasons'],
        'suggestedCancerTypesJson' => $result['suggestedCancerTypes'],
        'completedAt'              => now(),
    ]);

    $facility = $registration->facility;
    $navigator = $registration->navigator;
    $navigatorName = $navigator?->user
        ? trim(implode(' ', array_filter([
            $navigator->user->firstName,
            $navigator->user->lastName,
            $navigator->user->otherNames,
          ])))
        : null;


// Fire linkage notifications now that the assessment is complete —
// mirrors the same condition that gates whether facility info is
// shown to the client in this response.
if ($result['riskCategory'] !== 'low' && $facility) {
    ClientLinkedToScreeningCenter::dispatch(
        (object) [
            'fullName'    => $registration->fullName,
            'email'       => $registration->email,
            'phoneNumber' => $registration->phoneNumber,
        ],
        $facility,
    );
}


    return response()->json([
        'assessmentId'   => (string) $assessment->assessmentId,
        'riskCategory'   => $result['riskCategory'],
        'recommendation' => $result['recommendation'],
        'flaggedReasons' => $result['flaggedReasons'],
        'facility'       => ($result['riskCategory'] !== 'low' && $facility) ? [
            'facilityName'       => $facility->facilityName,
            'facilityAddress'    => $facility->facilityAddress,
            'navigatorName'      => $navigatorName ?: $facility->navigatorName,
            'navigatorPhone'     => $navigator?->user?->phoneNumber ?? $facility->navigatorPhone,
            'clinicHoursDisplay' => $facility->formatClinicHours(),
        ] : null,
    ], 201);
}
    /**
     * Stage 1 self-assessment records — for internal staff visibility,
     * not the public submission flow above.
     */
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
