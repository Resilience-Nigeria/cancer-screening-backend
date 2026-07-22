<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BreastScreeningController;
use App\Http\Controllers\Api\CaseOutcomeController;
use App\Http\Controllers\Api\CervicalScreeningController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ColorectalScreeningController;
use App\Http\Controllers\Api\LiverScreeningController;
use App\Http\Controllers\Api\ProstateScreeningController;
use App\Http\Controllers\Api\RiskProfileController;
use App\Http\Controllers\Api\ScreeningVisitController;
use App\Http\Controllers\Api\OutcomeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Api\FacilityController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\AwarenessRegistrationController;
use App\Http\Controllers\Api\SelfAssessmentController;

use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Controllers\Api\OtpController;

use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// routes/api.php
Route::get('/areas', function (\Illuminate\Http\Request $request) {
    $areas = DB::table('areaCoordinates')
        ->whereRaw('LOWER(state) = ?', [strtolower($request->state ?? '')])
        ->whereRaw('LOWER(lga) = ?',   [strtolower($request->lga   ?? '')])
        ->orderBy('area')
        ->pluck('area');

    return response()->json(['areas' => $areas]);
});

Route::post('/awareness/register', [AwarenessRegistrationController::class, 'store']);

// ── Client Portal ────────────────────────────────────────────────────
// Public: phone + OTP login (clients never set a password).
Route::post('/client-portal/send-otp', [App\Http\Controllers\Api\ClientAuthController::class, 'sendOtp']);
Route::post('/client-portal/verify-otp', [App\Http\Controllers\Api\ClientAuthController::class, 'verifyOtp']);

// Protected: scoped strictly to the authenticated client's own record.
Route::middleware('client.auth')->group(function () {
    Route::post('/client-portal/logout', [App\Http\Controllers\Api\ClientAuthController::class, 'logout']);
    Route::get('/client-portal/me', [App\Http\Controllers\Api\ClientPortalController::class, 'me']);
    Route::get('/client-portal/risk-profile', [App\Http\Controllers\Api\ClientPortalController::class, 'riskProfile']);
    Route::get('/client-portal/visits', [App\Http\Controllers\Api\ClientPortalController::class, 'visits']);
    Route::get('/client-portal/outcome', [App\Http\Controllers\Api\ClientPortalController::class, 'outcome']);
});

Route::post('/otp/send',   [OtpController::class, 'send']);
Route::post('/otp/verify', [OtpController::class, 'verify']);
Route::post('/otp/resend', [OtpController::class, 'resend']);

Route::post('/self-assessment', [SelfAssessmentController::class, 'store']);

Route::match(['GET', 'POST'], '/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle']);

Route::middleware(['auth:api', 'facility.scope'])->group(function () {
    Route::get('/user', function () {
        $user = auth()->user();
        return response()->json([
            'firstName' => $user->firstName,
            'lastName' => $user->lastName,
            'otherNames' => $user->otherNames,
            'email' => $user->email,
            'role' => $user->role,
            'roleName' => $user->user_role?->roleName,
            'id' => $user->id,
            'message' => 'User authenticated successfully',
            ]);
        });
            
    Route::get('/awareness/registrations', [AwarenessRegistrationController::class, 'index']);
    Route::get('/awareness/lookup', [AwarenessRegistrationController::class, 'lookupByPhone']);
    
    // routes/api.php — inside auth:api middleware group
Route::post('/clients/{client}/referrals', [ClientReferralController::class, 'store']);
Route::get('/referrals', [ClientReferralController::class, 'index']);
Route::patch('/referrals/{referral}/status', [ClientReferralController::class, 'updateStatus']);

    Route::get('/users/organizations', [UserController::class, 'getOrganizations']);
    
    Route::get('/clients', [ClientController::class, 'index']);
    Route::post('/clients', [ClientController::class, 'store']);
    Route::get('/clients/search/details', [ClientController::class, 'search']);
    Route::get('/clients/check-duplicate', [ClientController::class, 'checkDuplicate']);
    Route::get('/clients/linked', [ClientController::class, 'linked']);
    Route::get('/self-assessments', [SelfAssessmentController::class, 'index']);
    Route::get('/clients/{client}', [ClientController::class, 'show']);
    Route::patch('/clients/{client}', [ClientController::class, 'update']);

    Route::get('/clients/{client}/risk-profile', [RiskProfileController::class, 'show']);
    Route::post('/clients/{client}/risk-profile', [RiskProfileController::class, 'upsert']);

    Route::get('/clients/{client}/visits', [ScreeningVisitController::class, 'index']);
    Route::post('/clients/{client}/visits', [ScreeningVisitController::class, 'store']);
    Route::get('/visits/{visit}', [ScreeningVisitController::class, 'show']);
    Route::post('/visits/{visit}/examination', [ScreeningVisitController::class, 'storeExamination']);
    Route::post('/visits/{visit}/outcome', [ScreeningVisitController::class, 'classifyOutcome']);

    // ── Stage 3: Diagnostic Evaluation ──────────────────────────────────
    Route::get('/diagnostic-evaluations/pending-referrals', [App\Http\Controllers\Api\DiagnosticEvaluationController::class, 'pendingReferrals']);
    Route::get('/diagnostic-evaluations/client-context/{clientId}', [App\Http\Controllers\Api\DiagnosticEvaluationController::class, 'clientContext']);
    Route::post('/diagnostic-evaluations', [App\Http\Controllers\Api\DiagnosticEvaluationController::class, 'store']);
    Route::get('/diagnostic-evaluations/{evaluation}', [App\Http\Controllers\Api\DiagnosticEvaluationController::class, 'show']);
    Route::patch('/diagnostic-evaluations/{evaluation}', [App\Http\Controllers\Api\DiagnosticEvaluationController::class, 'update']);
    Route::post('/diagnostic-evaluations/{evaluation}/pathology', [App\Http\Controllers\Api\DiagnosticEvaluationController::class, 'finalizePathology']);
    Route::post('/diagnostic-evaluations/{evaluation}/decision', [App\Http\Controllers\Api\DiagnosticEvaluationController::class, 'classifyDecision']);

    // ── Stage 4: Treatment & Care Management ────────────────────────────
    Route::get('/treatment-plans/pending-evaluations', [App\Http\Controllers\Api\TreatmentPlanController::class, 'pendingEvaluations']);
    Route::get('/treatment-plans/client-context/{clientId}', [App\Http\Controllers\Api\TreatmentPlanController::class, 'clientContext']);
    Route::get('/treatment-plans', [App\Http\Controllers\Api\TreatmentPlanController::class, 'index']);
    Route::post('/treatment-plans', [App\Http\Controllers\Api\TreatmentPlanController::class, 'store']);
    Route::get('/treatment-plans/{plan}', [App\Http\Controllers\Api\TreatmentPlanController::class, 'show']);
    Route::patch('/treatment-plans/{plan}', [App\Http\Controllers\Api\TreatmentPlanController::class, 'update']);
    Route::post('/treatment-plans/{plan}/outcome', [App\Http\Controllers\Api\TreatmentPlanController::class, 'finalizeOutcome']);

    Route::get('/treatment-plans/{plan}/records', [App\Http\Controllers\Api\TreatmentRecordController::class, 'index']);
    Route::post('/treatment-plans/{plan}/records', [App\Http\Controllers\Api\TreatmentRecordController::class, 'store']);
    Route::patch('/treatment-records/{record}', [App\Http\Controllers\Api\TreatmentRecordController::class, 'update']);
    Route::delete('/treatment-records/{record}', [App\Http\Controllers\Api\TreatmentRecordController::class, 'destroy']);

    Route::get('/treatment-plans/{plan}/monitoring', [App\Http\Controllers\Api\TreatmentMonitoringController::class, 'index']);
    Route::post('/treatment-plans/{plan}/monitoring', [App\Http\Controllers\Api\TreatmentMonitoringController::class, 'store']);

    Route::get('/follow-up-schedules', [App\Http\Controllers\Api\FollowUpScheduleController::class, 'index']);
    Route::post('/follow-up-schedules/{schedule}/complete', [App\Http\Controllers\Api\FollowUpScheduleController::class, 'markCompleted']);

    Route::get('/visits/{visit}/cervical-screening', [CervicalScreeningController::class, 'show']);
    Route::post('/visits/{visit}/cervical-screening', [CervicalScreeningController::class, 'store']);

    Route::get('/visits/{visit}/breast-screening', [BreastScreeningController::class, 'show']);
    Route::post('/visits/{visit}/breast-screening', [BreastScreeningController::class, 'store']);

    Route::get('/visits/{visit}/colorectal-screening', [ColorectalScreeningController::class, 'show']);
    Route::post('/visits/{visit}/colorectal-screening', [ColorectalScreeningController::class, 'store']);

    Route::get('/visits/{visit}/liver-screening', [LiverScreeningController::class, 'show']);
    Route::post('/visits/{visit}/liver-screening', [LiverScreeningController::class, 'store']);

    Route::get('/visits/{visit}/prostate-screening', [ProstateScreeningController::class, 'show']);
    Route::post('/visits/{visit}/prostate-screening', [ProstateScreeningController::class, 'store']);

    Route::get('/clients/{client}/outcome', [CaseOutcomeController::class, 'show']);
    Route::put('/clients/{client}/outcome', [CaseOutcomeController::class, 'upsert']);

    Route::get('/visits', [ScreeningVisitController::class, 'index']);

    // routes/api.php
    Route::get('/dashboard/screenings', [DashboardController::class, 'allScreenings']);


    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
    Route::get('/dashboard/recent-activity', [DashboardController::class, 'getRecentActivity']);
    Route::get('/dashboard/monthly-trends', [DashboardController::class, 'getMonthlyTrends']);

    Route::get('/dashboard/referrals', [DashboardController::class, 'getReferrals']);
    Route::get('/dashboard/referred-clients', [DashboardController::class, 'getReferredClients']);
        Route::get('/dashboard/screenings/{type}', [DashboardController::class, 'getScreeningsByType']);
        Route::get('/dashboard/positive-findings', [DashboardController::class, 'getPositiveFindings']);


    Route::get('/outcomes', [OutcomeController::class, 'index']);
    Route::get('/outcomes/statistics', [OutcomeController::class, 'statistics']);


        // Facilities Management Routes
    Route::get('/facilities', [FacilityController::class, 'index']);
    Route::get('/facilities/states', [FacilityController::class, 'states']);
    Route::get('/facilities/{facility}', [FacilityController::class, 'show']);
    Route::post('/facilities', [FacilityController::class, 'store']);
    Route::put('/facilities/{facility}', [FacilityController::class, 'update']);
    Route::delete('/facilities/{facility}', [FacilityController::class, 'destroy']);

        // Users Management Routes
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/roles', [UserController::class, 'roles']);
    Route::get('/roles', [App\Http\Controllers\Api\RoleController::class, 'index']);
    Route::patch('/roles/{role}/scope', [App\Http\Controllers\Api\RoleController::class, 'updateScope']);
    Route::get('/settings', [App\Http\Controllers\Api\SettingsController::class, 'index']);
    Route::patch('/settings', [App\Http\Controllers\Api\SettingsController::class, 'update']);
    Route::get('/users/{user}/facility-grants', [App\Http\Controllers\Api\UserFacilityGrantController::class, 'index']);
    Route::post('/users/{user}/facility-grants', [App\Http\Controllers\Api\UserFacilityGrantController::class, 'store']);
    Route::delete('/users/{user}/facility-grants/{facilityId}', [App\Http\Controllers\Api\UserFacilityGrantController::class, 'destroy']);
    Route::get('/users/facilities', [UserController::class, 'facilities']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);


    // Cancer Analytics Routes

    Route::get('/analytics/cancer-by-age', [App\Http\Controllers\CancerAnalyticsController::class, 'getCancerByAgeClassification']);
    Route::get('/analytics/cancer-type-age-distribution/{cancerType}', [App\Http\Controllers\CancerAnalyticsController::class, 'getCancerTypeAgeDistribution']);
    Route::get('/analytics/gender-age-comparison', [App\Http\Controllers\CancerAnalyticsController::class, 'getGenderAgeComparison']);
    Route::get('/analytics/stage2-outcomes', [App\Http\Controllers\CancerAnalyticsController::class, 'getStage2OutcomeDistribution']);
    Route::get('/analytics/facility-performance', [App\Http\Controllers\CancerAnalyticsController::class, 'getFacilityPerformance']);
    Route::get('/analytics/referral-funnel', [App\Http\Controllers\CancerAnalyticsController::class, 'getReferralFunnel']);
    Route::get('/analytics/timing-metrics', [App\Http\Controllers\CancerAnalyticsController::class, 'getTimingMetrics']);
    Route::get('/analytics/treatment', [App\Http\Controllers\CancerAnalyticsController::class, 'getTreatmentAnalytics']);


});