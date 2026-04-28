<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpsertCaseOutcomeRequest;
use App\Models\CaseOutcome;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OutcomeController extends Controller
{
    /**
     * Get or create outcome for a specific client
     * 
     * @param int $clientId
     * @return JsonResponse
     */
    public function show(int $clientId): JsonResponse
    {
        $outcome = CaseOutcome::where('clientId', $clientId)->first();
        
        if (!$outcome) {
            return response()->json([
                'message' => 'No outcome found for this client',
                'data' => null
            ]);
        }
        
        return response()->json([
            'data' => $this->formatOutcome($outcome)
        ]);
    }
    
    /**
     * Update or create outcome for a client
     *
     * @param UpsertCaseOutcomeRequest $request
     * @param int $clientId
     * @return JsonResponse
     */
    public function update(UpsertCaseOutcomeRequest $request, int $clientId): JsonResponse
    {
        // Check if client exists
        $client = Client::find($clientId);
        if (!$client) {
            return response()->json([
                'message' => 'Client not found'
            ], 404);
        }
        
        // Get validated data
        $validated = $request->validated();
        
        // Filter out null values to avoid overwriting with null
        $validated = array_filter($validated, function ($value) {
            return $value !== null && $value !== '';
        });

        // Handle array fields (convert to JSON for storage)
        if (isset($validated['missedAppointmentReasons']) && is_array($validated['missedAppointmentReasons'])) {
            $validated['missedAppointmentReasons'] = json_encode($validated['missedAppointmentReasons']);
        }
        
        if (isset($validated['adherenceInterventions']) && is_array($validated['adherenceInterventions'])) {
            $validated['adherenceInterventions'] = json_encode($validated['adherenceInterventions']);
        }
        
        // Add clientId
        $validated['clientId'] = $clientId;
        
        // Update or create outcome
        $outcome = CaseOutcome::updateOrCreate(
            ['clientId' => $clientId],
            $validated
        );
        
        return response()->json([
            'message' => 'Case outcome saved successfully',
            'data' => $this->formatOutcome($outcome)
        ], 200);
    }
    
    /**
     * Display a listing of all case outcomes with filtering and search
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->input('limit', 10);
        $search = $request->input('search');
        $status = $request->input('status');
        
        $query = CaseOutcome::with('client')->orderBy('updated_at', 'desc');
        
        // Apply search filter
        if ($search) {
            $query->whereHas('client', function ($q) use ($search) {
                $q->where('fullName', 'like', "%{$search}%")
                  ->orWhere('screeningId', 'like', "%{$search}%");
            });
        }
        
        // Apply status filters
        if ($status) {
            switch ($status) {
                case 'positive':
                    $query->where('screeningResult', 'positive');
                    break;
                case 'negative':
                    $query->where('screeningResult', 'negative');
                    break;
                case 'in_treatment':
                    $query->where('treatmentCommenced', 'yes')
                          ->whereNotIn('treatmentStatus', ['completed', 'discontinued']);
                    break;
                case 'completed':
                    $query->where('treatmentStatus', 'completed');
                    break;
                case 'follow_up':
                    $query->where('screeningResult', 'negative')
                          ->where(function ($q) {
                              $q->whereNull('followUpEstablished')
                                ->orWhere('followUpEstablished', '!=', 'yes');
                          });
                    break;
            }
        }
        
        $outcomes = $query->paginate($perPage);
        
        return response()->json([
            'data' => $outcomes->map(function ($outcome) {
                return $this->formatOutcome($outcome);
            }),
            'total' => $outcomes->total(),
            'per_page' => $outcomes->perPage(),
            'current_page' => $outcomes->currentPage(),
            'last_page' => $outcomes->lastPage(),
        ]);
    }
    
    /**
     * Get statistics for outcomes dashboard
     *
     * @return JsonResponse
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_outcomes' => CaseOutcome::count(),
            'positive_screening' => CaseOutcome::where('screeningResult', 'positive')->count(),
            'negative_screening' => CaseOutcome::where('screeningResult', 'negative')->count(),
            'inconclusive_screening' => CaseOutcome::where('screeningResult', 'inconclusive')->count(),
            'in_treatment' => CaseOutcome::where('treatmentCommenced', 'yes')
                ->whereNotIn('treatmentStatus', ['completed', 'discontinued'])
                ->count(),
            'treatment_completed' => CaseOutcome::where('treatmentStatus', 'completed')->count(),
            'treatment_discontinued' => CaseOutcome::where('treatmentStatus', 'discontinued')->count(),
            'follow_up_required' => CaseOutcome::where('screeningResult', 'negative')
                ->where(function ($q) {
                    $q->whereNull('followUpEstablished')
                      ->orWhere('followUpEstablished', '!=', 'yes');
                })
                ->count(),
            'complete_remission' => CaseOutcome::where('clinicalOutcome', 'complete_remission')->count(),
            'partial_remission' => CaseOutcome::where('clinicalOutcome', 'partial_remission')->count(),
            'stable_disease' => CaseOutcome::where('clinicalOutcome', 'stable_disease')->count(),
            'progressive_disease' => CaseOutcome::where('clinicalOutcome', 'progressive_disease')->count(),
            'recurrence' => CaseOutcome::where('clinicalOutcome', 'recurrence')->count(),
            'death' => CaseOutcome::where('clinicalOutcome', 'death')->count(),
        ];
        
        return response()->json($stats);
    }
    
    /**
     * Format outcome for API response (both snake_case and camelCase)
     *
     * @param CaseOutcome $outcome
     * @return array
     */
    private function formatOutcome(CaseOutcome $outcome): array
    {
        // Decode JSON fields
        $missedAppointmentReasons = [];
        if ($outcome->missedAppointmentReasons) {
            $missedAppointmentReasons = is_string($outcome->missedAppointmentReasons) 
                ? json_decode($outcome->missedAppointmentReasons, true) 
                : $outcome->missedAppointmentReasons;
        }
        
        $adherenceInterventions = [];
        if ($outcome->adherenceInterventions) {
            $adherenceInterventions = is_string($outcome->adherenceInterventions) 
                ? json_decode($outcome->adherenceInterventions, true) 
                : $outcome->adherenceInterventions;
        }
        
        return [
            // Snake case for backend compatibility
            'outcome_id' => $outcome->id,
            'client_id' => $outcome->clientId,
            'pre_screening_counseling_date' => $outcome->preScreeningCounselingDate,
            'pre_screening_counselor' => $outcome->preScreeningCounselor,
            'pre_screening_consent' => $outcome->preScreeningConsent,
            'screening_result' => $outcome->screeningResult,
            'screening_date' => $outcome->screeningDate,
            'post_screening_counseling_date' => $outcome->postScreeningCounselingDate,
            'post_screening_counselor' => $outcome->postScreeningCounselor,
            'next_follow_up_date' => $outcome->nextFollowUpDate,
            'follow_up_established' => $outcome->followUpEstablished,
            'diagnosis' => $outcome->diagnosis,
            'cancer_type' => $outcome->cancerType,
            'cancer_stage' => $outcome->cancerStage,
            'staging_comments' => $outcome->stagingComments,
            'diagnosis_date' => $outcome->diagnosisDate,
            'treatment_commenced' => $outcome->treatmentCommenced,
            'treatment_commencement_date' => $outcome->treatmentCommencementDate,
            'treatment_delay_reason' => $outcome->treatmentDelayReason,
            'treatment_type' => $outcome->treatmentType,
            'treatment_facility' => $outcome->treatmentFacility,
            'adherence_rating' => $outcome->adherenceRating,
            'missed_appointments' => $outcome->missedAppointments,
            'missed_appointment_reasons' => $missedAppointmentReasons,
            'adherence_interventions' => $adherenceInterventions,
            'treatment_status' => $outcome->treatmentStatus,
            'treatment_completion_date' => $outcome->treatmentCompletionDate,
            'discontinuation_reason' => $outcome->discontinuationReason,
            'treatment_duration' => $outcome->treatmentDuration,
            'clinical_outcome' => $outcome->clinicalOutcome,
            'outcome_assessment_date' => $outcome->outcomeAssessmentDate,
            'remarks' => $outcome->remarks,
            'cancer_confirmed' => $outcome->cancerConfirmed,
            'linkage_to_treatment' => $outcome->linkageToTreatment,
            'treatment_completed' => $outcome->treatmentCompleted,
            
            // Camel case for frontend compatibility
            'outcomeId' => $outcome->id,
            'clientId' => $outcome->clientId,
            'preScreeningCounselingDate' => $outcome->preScreeningCounselingDate,
            'preScreeningCounselor' => $outcome->preScreeningCounselor,
            'preScreeningConsent' => $outcome->preScreeningConsent,
            'screeningResult' => $outcome->screeningResult,
            'screeningDate' => $outcome->screeningDate,
            'postScreeningCounselingDate' => $outcome->postScreeningCounselingDate,
            'postScreeningCounselor' => $outcome->postScreeningCounselor,
            'nextFollowUpDate' => $outcome->nextFollowUpDate,
            'followUpEstablished' => $outcome->followUpEstablished,
            'cancerType' => $outcome->cancerType,
            'cancerStage' => $outcome->cancerStage,
            'stagingComments' => $outcome->stagingComments,
            'diagnosisDate' => $outcome->diagnosisDate,
            'treatmentCommenced' => $outcome->treatmentCommenced,
            'treatmentCommencementDate' => $outcome->treatmentCommencementDate,
            'treatmentDelayReason' => $outcome->treatmentDelayReason,
            'treatmentType' => $outcome->treatmentType,
            'treatmentFacility' => $outcome->treatmentFacility,
            'adherenceRating' => $outcome->adherenceRating,
            'missedAppointments' => $outcome->missedAppointments,
            'missedAppointmentReasons' => $missedAppointmentReasons,
            'adherenceInterventions' => $adherenceInterventions,
            'treatmentStatus' => $outcome->treatmentStatus,
            'treatmentCompletionDate' => $outcome->treatmentCompletionDate,
            'discontinuationReason' => $outcome->discontinuationReason,
            'treatmentDuration' => $outcome->treatmentDuration,
            'clinicalOutcome' => $outcome->clinicalOutcome,
            'outcomeAssessmentDate' => $outcome->outcomeAssessmentDate,
            'cancerConfirmed' => $outcome->cancerConfirmed,
            'linkageToTreatment' => $outcome->linkageToTreatment,
            'treatmentCompleted' => $outcome->treatmentCompleted,
            
            // Client info
            'client' => $outcome->client ? [
                'fullName' => $outcome->client->fullName,
                'full_name' => $outcome->client->fullName,
                'screeningId' => $outcome->client->screeningId,
                'screening_id' => $outcome->client->screeningId,
            ] : null,
        ];
    }
}