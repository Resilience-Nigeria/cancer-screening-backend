<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSelfAssessmentRequest;
use App\Models\AwarenessRegistration;
use App\Models\SelfAssessment;
use App\Services\RiskClassificationService;
use Illuminate\Http\JsonResponse;

class SelfAssessmentController extends Controller
{
    public function __construct(protected RiskClassificationService $classifier) {}

    public function store(StoreSelfAssessmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        $registration = AwarenessRegistration::findOrFail($data['registrationId']);

        $result = $this->classifier->classify($data['answers'], $registration->gender);

        $assessment = SelfAssessment::create([
            'registrationId' => $registration->registrationId,
            'clientId' => $registration->clientId,
            'answersJson' => $data['answers'],
            'riskCategory' => $result['riskCategory'],
            'recommendation' => $result['recommendation'],
            'flaggedReasonsJson' => $result['flaggedReasons'],
            'suggestedCancerTypesJson' => $result['suggestedCancerTypes'],
            'completedAt' => now(),
        ]);

        return response()->json([
            'message' => 'Self-assessment completed.',
            'assessmentId' => $assessment->assessmentId,
            'riskCategory' => $result['riskCategory'],
            'recommendation' => $result['recommendation'],
            'flaggedReasons' => $result['flaggedReasons'],
            'suggestedCancerTypes' => $result['suggestedCancerTypes'],
            'facility' => $registration->facility ? [
                'facilityName' => $registration->facility->facilityName,
                'facilityAddress' => $registration->facility->facilityAddress,
                'navigatorName' => $registration->facility->navigatorName,
                'navigatorPhone' => $registration->facility->navigatorPhone,
                'clinicHoursDisplay' => $registration->facility->formatClinicHours(),
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
