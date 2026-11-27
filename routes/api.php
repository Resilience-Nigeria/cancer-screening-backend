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
use App\Http\Controllers\Api\ClientReferralController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ClientVisitController;

use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\NavigatorClientsController;
use App\Http\Controllers\Api\QrCodeController;

use App\Http\Controllers\Api\HpvSampleController;

use App\Http\Controllers\Api\ScreeningSampleController;
use App\Http\Controllers\Api\SampleDeletionController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\OverdueResultsController;

use App\Http\Controllers\Api\ScreenedClientController;
use App\Http\Controllers\Api\ReferredClientController;
use App\Http\Controllers\Api\ReportSettingsController;

use App\Http\Controllers\Api\{FollowUpController, ResultCommunicationController, StaffDeviceController,
    StockController, StaffChatController, MobileSupportController};
use App\Http\Controllers\Api\AppReleaseController;

use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::get('/qr-code', [QrCodeController::class, 'index']);
// routes/api.php
Route::get('/areas', function (\Illuminate\Http\Request $request) {
    $areas = DB::table('areaCoordinates')
        ->whereRaw('LOWER(TRIM(state)) = ?', [strtolower(trim($request->state ?? ''))])
        ->whereRaw('LOWER(TRIM(lga)) = ?',   [strtolower(trim($request->lga   ?? ''))])
        ->orderBy('area')
        ->pluck('area');

    return response()->json(['areas' => $areas]);
});

/**
 * Debug helper — shows exactly what the seeded state/lga values look
 * like for a given state, so mismatches against the frontend's
 * hardcoded state/LGA list (extra spaces, different spelling, etc.)
 * are visible directly instead of guessing.
 */
Route::get('/areas/debug', function (\Illuminate\Http\Request $request) {
    $query = DB::table('areaCoordinates');

    if ($request->state) {
        $query->whereRaw('LOWER(TRIM(state)) = ?', [strtolower(trim($request->state))]);
    }

    $distinctLgas = (clone $query)
        ->select('lga', DB::raw('count(*) as area_count'))
        ->groupBy('lga')
        ->orderBy('lga')
        ->get();

    return response()->json([
        'distinctStatesSeeded' => DB::table('areaCoordinates')->distinct()->pluck('state'),
        'lgasForThisState' => $distinctLgas,
    ]);
});

Route::post('/awareness/register', [AwarenessRegistrationController::class, 'store']);
Route::post('/awareness/{registrationId}/self-assessment', [SelfAssessmentController::class, 'store']);
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

// Route::post('/self-assessment', [SelfAssessmentController::class, 'store']);


Route::match(['GET', 'POST'], '/webhooks/whatsapp', [WhatsAppWebhookController::class, 'handle']);

// ── Mobile app: public (no sign-in) ────────────────────────────────────
Route::get('/mobile/app-version', [\App\Http\Controllers\Api\AppReleaseController::class, 'latest']);
Route::get('/mobile/app-latest', [\App\Http\Controllers\Api\AppReleaseController::class, 'latestApk']);
Route::get('/mobile-app/download/{id}/{token}', [\App\Http\Controllers\Api\AppReleaseController::class, 'download'])->whereNumber('id');
Route::get('/staff-photos/{file}', [\App\Http\Controllers\Api\ProfilePhotoController::class, 'file']);

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

    Route::post('/visits/{visitId}/breast-sync', [\App\Http\Controllers\Api\BreastSyncController::class, 'sync'])->whereNumber('visitId');
Route::patch('/screening-samples/{id}/details', [\App\Http\Controllers\Api\SampleCorrectionController::class, 'update'])->whereNumber('id');
Route::get('/identifiers/lookup', [\App\Http\Controllers\Api\IdentifierController::class, 'lookup']);
Route::get('/clients/{clientId}/identifiers', [\App\Http\Controllers\Api\IdentifierController::class, 'forClient']);
Route::get('/ai/status', [\App\Http\Controllers\Api\ReportSummaryController::class, 'status']);
Route::post('/ai/report-summary', [\App\Http\Controllers\Api\ReportSummaryController::class, 'summarise']);

// ══ Mobile app, staff notifications, chat, stock (restored) ══════════
    // Client / sample lists (must stay above /clients/{client})
    Route::get('/clients/list', [\App\Http\Controllers\Api\ClientListController::class, 'index']);
    Route::get('/clients/mine', [\App\Http\Controllers\Api\MyClientsController::class, 'index']);
    Route::get('/screening-samples/list', [\App\Http\Controllers\Api\SampleListController::class, 'index']);

    // Phone notifications
    Route::post('/mobile/push-token', [\App\Http\Controllers\Api\MobileNotificationController::class, 'registerToken']);
    Route::delete('/mobile/push-token', [\App\Http\Controllers\Api\MobileNotificationController::class, 'removeToken']);
    Route::get('/mobile/notifications', [\App\Http\Controllers\Api\MobileNotificationController::class, 'index']);
    Route::get('/mobile/notifications/unread-count', [\App\Http\Controllers\Api\MobileNotificationController::class, 'unreadCount']);
    Route::post('/mobile/notifications/read-all', [\App\Http\Controllers\Api\MobileNotificationController::class, 'markAllRead']);
    Route::post('/mobile/notifications/{id}/read', [\App\Http\Controllers\Api\MobileNotificationController::class, 'markRead'])->whereNumber('id');
    Route::post('/mobile/push-test', [\App\Http\Controllers\Api\MobileNotificationController::class, 'test']);

    // Staff notifications (web: send, list, edit, resend, withdraw)
    Route::get('/staff-notifications', [\App\Http\Controllers\Api\StaffBroadcastController::class, 'index']);
    Route::get('/staff-notifications/options', [\App\Http\Controllers\Api\StaffBroadcastController::class, 'options']);
    Route::post('/staff-notifications/preview', [\App\Http\Controllers\Api\StaffBroadcastController::class, 'preview']);
    Route::post('/staff-notifications', [\App\Http\Controllers\Api\StaffBroadcastController::class, 'send']);
    Route::get('/staff-notifications/{id}/recipients', [\App\Http\Controllers\Api\StaffBroadcastController::class, 'recipients'])->whereNumber('id');
    Route::put('/staff-notifications/{id}', [\App\Http\Controllers\Api\StaffBroadcastController::class, 'update'])->whereNumber('id');
    Route::post('/staff-notifications/{id}/resend', [\App\Http\Controllers\Api\StaffBroadcastController::class, 'resend'])->whereNumber('id');
    Route::delete('/staff-notifications/{id}', [\App\Http\Controllers\Api\StaffBroadcastController::class, 'withdraw'])->whereNumber('id');

    // Follow-ups, SMS, result communication
    Route::get('/mobile/follow-ups', [\App\Http\Controllers\Api\FollowUpController::class, 'index']);
    Route::get('/mobile/follow-ups/history', [\App\Http\Controllers\Api\FollowUpController::class, 'history']);
    Route::post('/mobile/follow-ups/contact', [\App\Http\Controllers\Api\FollowUpController::class, 'contact']);
    Route::post('/mobile/sms', [\App\Http\Controllers\Api\FollowUpController::class, 'sms']);
    Route::get('/mobile/results/communicated', [\App\Http\Controllers\Api\ResultCommunicationController::class, 'forClient']);
    Route::get('/mobile/results/{sampleId}/communication', [\App\Http\Controllers\Api\ResultCommunicationController::class, 'show'])->whereNumber('sampleId');
    Route::post('/mobile/results/{sampleId}/communication', [\App\Http\Controllers\Api\ResultCommunicationController::class, 'store'])->whereNumber('sampleId');

    // Phones (heartbeat, remote sign-out / wipe)
    Route::post('/mobile/devices/heartbeat', [\App\Http\Controllers\Api\StaffDeviceController::class, 'heartbeat']);
    Route::post('/mobile/devices/done', [\App\Http\Controllers\Api\StaffDeviceController::class, 'done']);
    Route::get('/mobile-admin/devices', [\App\Http\Controllers\Api\StaffDeviceController::class, 'index']);
    Route::post('/mobile-admin/devices/{id}/sign-out', [\App\Http\Controllers\Api\StaffDeviceController::class, 'signOut'])->whereNumber('id');
    Route::post('/mobile-admin/devices/{id}/wipe', [\App\Http\Controllers\Api\StaffDeviceController::class, 'wipe'])->whereNumber('id');
    Route::post('/mobile-admin/devices/{id}/cancel', [\App\Http\Controllers\Api\StaffDeviceController::class, 'cancel'])->whereNumber('id');

    // Stock (phone) and Stock Management (web)
    Route::get('/stock', [\App\Http\Controllers\Api\StockController::class, 'index']);
    Route::get('/stock/overview', [\App\Http\Controllers\Api\StockController::class, 'overview']);
    Route::get('/stock/movements', [\App\Http\Controllers\Api\StockController::class, 'movements']);
    Route::get('/stock/check', [\App\Http\Controllers\Api\StockController::class, 'check']);
    Route::post('/stock/receive', [\App\Http\Controllers\Api\StockController::class, 'receive']);
    Route::post('/stock/adjust', [\App\Http\Controllers\Api\StockController::class, 'adjust']);
    Route::post('/stock/expired', [\App\Http\Controllers\Api\StockController::class, 'expired']);
    Route::post('/stock/transfer', [\App\Http\Controllers\Api\StockController::class, 'transfer']);
    Route::put('/stock/level', [\App\Http\Controllers\Api\StockController::class, 'setLowLevel']);
    Route::get('/stock-admin/meta', [\App\Http\Controllers\Api\StockAdminController::class, 'meta']);
    Route::get('/stock-admin/matrix', [\App\Http\Controllers\Api\StockAdminController::class, 'matrix']);
    Route::post('/stock-admin/receive-bulk', [\App\Http\Controllers\Api\StockAdminController::class, 'receiveBulk']);
    Route::get('/stock-admin/movements', [\App\Http\Controllers\Api\StockAdminController::class, 'movements']);
    Route::get('/stock-admin/movements/export', [\App\Http\Controllers\Api\StockAdminController::class, 'export']);
    Route::get('/stock-admin/expiring', [\App\Http\Controllers\Api\StockAdminController::class, 'expiring']);
    Route::get('/stock-admin/items', [\App\Http\Controllers\Api\StockAdminController::class, 'items']);
    Route::post('/stock-admin/items', [\App\Http\Controllers\Api\StockAdminController::class, 'storeItem']);
    Route::put('/stock-admin/items/{id}', [\App\Http\Controllers\Api\StockAdminController::class, 'updateItem'])->whereNumber('id');

    // Staff chat
    Route::get('/mobile/staff', [\App\Http\Controllers\Api\StaffChatController::class, 'directory']);
    Route::get('/mobile/staff/{id}', [\App\Http\Controllers\Api\StaffChatController::class, 'profile'])->whereNumber('id');
    Route::get('/mobile/chats', [\App\Http\Controllers\Api\StaffChatController::class, 'index']);
    Route::get('/mobile/chats/unread-count', [\App\Http\Controllers\Api\StaffChatController::class, 'unreadCount']);
    Route::post('/mobile/chats', [\App\Http\Controllers\Api\StaffChatController::class, 'open']);
    Route::get('/mobile/chats/{id}/messages', [\App\Http\Controllers\Api\StaffChatController::class, 'messages'])->whereNumber('id');
    Route::post('/mobile/chats/{id}/messages', [\App\Http\Controllers\Api\StaffChatController::class, 'send'])->whereNumber('id');
    Route::post('/mobile/chats/{id}/read', [\App\Http\Controllers\Api\StaffChatController::class, 'read'])->whereNumber('id');

    // Help, problem reports, crash reports
    Route::post('/mobile/support-tickets', [\App\Http\Controllers\Api\MobileSupportController::class, 'report']);
    Route::post('/mobile/error-reports', [\App\Http\Controllers\Api\MobileSupportController::class, 'errors']);
    Route::get('/mobile-admin/tickets', [\App\Http\Controllers\Api\MobileSupportController::class, 'tickets']);
    Route::post('/mobile-admin/tickets/{id}/resolve', [\App\Http\Controllers\Api\MobileSupportController::class, 'resolve'])->whereNumber('id');
    Route::get('/mobile-admin/errors', [\App\Http\Controllers\Api\MobileSupportController::class, 'errorSummary']);

    // Profile photo
    Route::get('/mobile/profile/photo', [\App\Http\Controllers\Api\ProfilePhotoController::class, 'show']);
    Route::post('/mobile/profile/photo', [\App\Http\Controllers\Api\ProfilePhotoController::class, 'store']);
    Route::delete('/mobile/profile/photo', [\App\Http\Controllers\Api\ProfilePhotoController::class, 'destroy']);

    // App versions (web upload) and the "Get the app" prompt
    Route::get('/mobile-admin/releases', [\App\Http\Controllers\Api\AppReleaseController::class, 'index']);
    Route::post('/mobile-admin/releases', [\App\Http\Controllers\Api\AppReleaseController::class, 'store']);
    Route::patch('/mobile-admin/releases/{id}', [\App\Http\Controllers\Api\AppReleaseController::class, 'update'])->whereNumber('id');
    Route::delete('/mobile-admin/releases/{id}', [\App\Http\Controllers\Api\AppReleaseController::class, 'destroy'])->whereNumber('id');
    Route::get('/mobile/app-promo', [\App\Http\Controllers\Api\MobileAppPromoController::class, 'forMe']);
    Route::get('/mobile-admin/app-promo', [\App\Http\Controllers\Api\MobileAppPromoController::class, 'show']);
    Route::put('/mobile-admin/app-promo', [\App\Http\Controllers\Api\MobileAppPromoController::class, 'update']);
    // ══ end of restored block ═════════════════════════════════════════



        Route::get('/services', [ServiceController::class, 'index']);
        Route::get('/services/{slug}/facilities', [ServiceController::class, 'facilitiesForService']);
        Route::post('/clients/{clientId}/bookings', [BookingController::class, 'store']);

        Route::get('/clients/{clientId}/visits/incomplete', [ClientVisitController::class, 'incomplete']);

    Route::get('/navigator/clients', [NavigatorClientsController::class, 'index']);

    Route::get('/awareness/registrations', [AwarenessRegistrationController::class, 'index']);
    Route::get('/awareness/lookup', [AwarenessRegistrationController::class, 'lookupByPhone']);
    
    // routes/api.php — inside auth:api middleware group
Route::post('/clients/{client}/referrals', [ClientReferralController::class, 'store']);
Route::get('/referrals', [ClientReferralController::class, 'index']);
Route::patch('/referrals/{referral}/status', [ClientReferralController::class, 'updateStatus']);


Route::get('/hpv-samples', [HpvSampleController::class, 'index']);
Route::post('/clients/{clientId}/hpv-samples', [HpvSampleController::class, 'store']);
Route::patch('/hpv-samples/{id}/result', [HpvSampleController::class, 'recordResult']);

// Deletion workflow (fixed paths first)
Route::get('/screening-samples/overdue', [OverdueResultsController::class, 'index']);
Route::get('/screening-samples/deletion-access', [SampleDeletionController::class, 'access']);
Route::get('/screening-samples/deletion-requests', [SampleDeletionController::class, 'requests']);
Route::post('/screening-samples/deletion-requests/{requestId}/decide', [SampleDeletionController::class, 'decide']);
Route::get('/screening-samples/deleted', [SampleDeletionController::class, 'deleted']);
Route::post('/screening-samples/{id}/deletion-request', [SampleDeletionController::class, 'requestDeletion']);
Route::post('/screening-samples/{id}/restore', [SampleDeletionController::class, 'restore']);
Route::delete('/screening-samples/{id}', [SampleDeletionController::class, 'destroy']);

Route::get('/screening-samples', [ScreeningSampleController::class, 'index']);
Route::post('/clients/{clientId}/screening-samples', [ScreeningSampleController::class, 'store']);
Route::post('/clients/{clientId}/screening-samples/book', [ScreeningSampleController::class, 'book']);
Route::patch('/screening-samples/{id}/result', [ScreeningSampleController::class, 'recordResult']);
Route::get('/screening-samples/results', [ScreeningSampleController::class, 'results']);

Route::post('/screening-samples/{id}/attachment', [ScreeningSampleController::class, 'uploadAttachment']);
Route::get('/screening-samples/{id}/attachment', [ScreeningSampleController::class, 'attachment']);
Route::delete('/screening-samples/{id}/attachment', [ScreeningSampleController::class, 'deleteAttachment']);

Route::get('/referred-clients', [ReferredClientController::class, 'index']);
Route::patch('/referred-clients/{referral}/status', [ClientReferralController::class, 'updateStatus']);


Route::get('/screened-clients', [ScreenedClientController::class, 'index']);
Route::get('/activity-logs', [ActivityLogController::class, 'index']);

// Report Settings: scheduled emails (weekly summary, overdue results) and who gets them. Super / NICRAT admins.
Route::get('/report-settings', [ReportSettingsController::class, 'index']);
Route::get('/report-settings/users', [ReportSettingsController::class, 'users']);
Route::patch('/report-settings/recipients/{id}', [ReportSettingsController::class, 'updateRecipient'])->whereNumber('id');
Route::delete('/report-settings/recipients/{id}', [ReportSettingsController::class, 'removeRecipient'])->whereNumber('id');
Route::put('/report-settings/{key}', [ReportSettingsController::class, 'update']);
Route::post('/report-settings/{key}/recipients', [ReportSettingsController::class, 'addRecipient']);
Route::get('/report-settings/{key}/audience', [ReportSettingsController::class, 'audience']);
Route::get('/report-settings/{key}/preview', [ReportSettingsController::class, 'preview']);
Route::post('/report-settings/{key}/send-test', [ReportSettingsController::class, 'sendTest']);
Route::post('/report-settings/{key}/send-now', [ReportSettingsController::class, 'sendNow']);
Route::post('/visits/{visitId}/lab-sync', [ScreeningSampleController::class, 'syncFromVisit']);

    Route::get('/users/organizations', [UserController::class, 'getOrganizations']);
    
    Route::get('/clients', [ClientController::class, 'index']);
    Route::post('/clients', [ClientController::class, 'store']);
    Route::get('/clients/search/details', [ClientController::class, 'search']);
    Route::get('/clients/check-duplicate', [ClientController::class, 'checkDuplicate']);

        Route::get('/clients/list', [\App\Http\Controllers\Api\ClientListController::class, 'index']);
    Route::get('/clients/mine', [\App\Http\Controllers\Api\MyClientsController::class, 'index']);
    Route::get('/screening-samples/list', [\App\Http\Controllers\Api\SampleListController::class, 'index']);

    Route::get('/clients/linked', [ClientController::class, 'linked']);
    Route::get('/self-assessments', [SelfAssessmentController::class, 'index']);
    Route::get('/clients/id-change-access', [ClientController::class, 'idChangeAccess']);
    Route::patch('/clients/{clientId}/residence', [ClientController::class, 'changeResidence']);
    Route::get('/clients/{client}', [ClientController::class, 'show']);
    Route::patch('/clients/{client}', [ClientController::class, 'update']);

    Route::get('/clients/{client}/risk-profile', [RiskProfileController::class, 'show']);
    Route::post('/clients/{client}/risk-profile', [RiskProfileController::class, 'upsert']);

    Route::get('/clients/{client}/visits', [ScreeningVisitController::class, 'index']);
    Route::post('/clients/{client}/visits', [\App\Http\Controllers\Api\VisitStartController::class, 'store']);
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
    Route::get('/facilities/map', [FacilityController::class, 'map']);
    Route::get('/facilities/{facility}', [FacilityController::class, 'show']);
    Route::post('/facilities', [FacilityController::class, 'store']);
    Route::put('/facilities/{facility}', [FacilityController::class, 'update']);
    Route::delete('/facilities/{facility}', [FacilityController::class, 'destroy']);

        // Users Management Routes
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/roles', [UserController::class, 'roles']);
    Route::get('/roles', [App\Http\Controllers\Api\RoleController::class, 'index']);
    Route::patch('/roles/{role}/scope', [App\Http\Controllers\Api\RoleController::class, 'updateScope']);
    Route::post('/roles', [App\Http\Controllers\Api\RoleController::class, 'store']);
    Route::put('/roles/{role}', [App\Http\Controllers\Api\RoleController::class, 'update']);
    Route::delete('/roles/{role}', [App\Http\Controllers\Api\RoleController::class, 'destroy']);
    Route::get('/settings', [App\Http\Controllers\Api\SettingsController::class, 'index']);
    Route::patch('/settings', [App\Http\Controllers\Api\SettingsController::class, 'update']);
    Route::get('/notification-providers', [App\Http\Controllers\Api\NotificationProviderController::class, 'index']);
    Route::post('/notification-providers', [App\Http\Controllers\Api\NotificationProviderController::class, 'store']);
    Route::patch('/notification-providers/{provider}', [App\Http\Controllers\Api\NotificationProviderController::class, 'update']);
    Route::post('/notification-providers/{provider}/default', [App\Http\Controllers\Api\NotificationProviderController::class, 'setDefault']);
    Route::delete('/notification-providers/{provider}', [App\Http\Controllers\Api\NotificationProviderController::class, 'destroy']);
    Route::get('/menu-visibility', [App\Http\Controllers\Api\MenuVisibilityController::class, 'index']);
    Route::patch('/menu-visibility', [App\Http\Controllers\Api\MenuVisibilityController::class, 'update']);
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
    Route::get('/analytics/lab-findings', [DashboardController::class, 'getLabAnalytics']);
    Route::get('/analytics/stage2-summary', [DashboardController::class, 'getStage2Summary']);
    Route::get('/analytics/facility-summary', [DashboardController::class, 'getFacilitySummary']);
    Route::get('/analytics/journey-funnel', [DashboardController::class, 'getJourneyFunnel']);
    Route::get('/analytics/socioeconomic', [App\Http\Controllers\CancerAnalyticsController::class, 'getSocioeconomicAnalytics']);
    Route::get('/indigency-support', [App\Http\Controllers\Api\IndigencySupportController::class, 'index']);


    // NCSR Mobile App: push registration + notification bell
    Route::post('/mobile/push-token', [\App\Http\Controllers\Api\MobileNotificationController::class, 'registerToken']);
    Route::delete('/mobile/push-token', [\App\Http\Controllers\Api\MobileNotificationController::class, 'removeToken']);
    Route::get('/mobile/notifications', [\App\Http\Controllers\Api\MobileNotificationController::class, 'index']);
    Route::get('/mobile/notifications/unread-count', [\App\Http\Controllers\Api\MobileNotificationController::class, 'unreadCount']);
    Route::post('/mobile/notifications/read-all', [\App\Http\Controllers\Api\MobileNotificationController::class, 'markAllRead']);
    Route::post('/mobile/notifications/{id}/read', [\App\Http\Controllers\Api\MobileNotificationController::class, 'markRead'])->whereNumber('id');
    Route::post('/mobile/push-test', [\App\Http\Controllers\Api\MobileNotificationController::class, 'test']);

    // Web dashboard: send notifications to staff (super admin / NICRAT admin)
    Route::get('/staff-notifications', [\App\Http\Controllers\Api\StaffBroadcastController::class, 'index']);
    Route::get('/staff-notifications/options', [\App\Http\Controllers\Api\StaffBroadcastController::class, 'options']);
    Route::post('/staff-notifications/preview', [\App\Http\Controllers\Api\StaffBroadcastController::class, 'preview']);
    Route::post('/staff-notifications', [\App\Http\Controllers\Api\StaffBroadcastController::class, 'send']);

  Route::get('/staff-notifications/{id}/recipients', [\App\Http\Controllers\Api\StaffBroadcastController::class, 'recipients'])->whereNumber('id');
    Route::put('/staff-notifications/{id}', [\App\Http\Controllers\Api\StaffBroadcastController::class, 'update'])->whereNumber('id');
    Route::post('/staff-notifications/{id}/resend', [\App\Http\Controllers\Api\StaffBroadcastController::class, 'resend'])->whereNumber('id');
    Route::delete('/staff-notifications/{id}', [\App\Http\Controllers\Api\StaffBroadcastController::class, 'withdraw'])->whereNumber('id');

    // Follow-ups & recalls + SMS to clients
Route::get('/mobile/follow-ups', [FollowUpController::class, 'index']);
Route::get('/mobile/follow-ups/history', [FollowUpController::class, 'history']);
Route::post('/mobile/follow-ups/contact', [FollowUpController::class, 'contact']);
Route::post('/mobile/sms', [FollowUpController::class, 'sms']);

// Results back to the client
Route::get('/mobile/results/communicated', [ResultCommunicationController::class, 'forClient']);
Route::get('/mobile/results/{sampleId}/communication', [ResultCommunicationController::class, 'show'])->whereNumber('sampleId');
Route::post('/mobile/results/{sampleId}/communication', [ResultCommunicationController::class, 'store'])->whereNumber('sampleId');

// Phone security (heartbeat, remote sign-out / wipe)
Route::post('/mobile/devices/heartbeat', [StaffDeviceController::class, 'heartbeat']);
Route::post('/mobile/devices/done', [StaffDeviceController::class, 'done']);
Route::get('/mobile-admin/devices', [StaffDeviceController::class, 'index']);
Route::post('/mobile-admin/devices/{id}/sign-out', [StaffDeviceController::class, 'signOut'])->whereNumber('id');
Route::post('/mobile-admin/devices/{id}/wipe', [StaffDeviceController::class, 'wipe'])->whereNumber('id');
Route::post('/mobile-admin/devices/{id}/cancel', [StaffDeviceController::class, 'cancel'])->whereNumber('id');

// Stock
Route::get('/stock', [StockController::class, 'index']);
Route::get('/stock/overview', [StockController::class, 'overview']);
Route::get('/stock/movements', [StockController::class, 'movements']);
Route::get('/stock/check', [StockController::class, 'check']);
Route::post('/stock/receive', [StockController::class, 'receive']);
Route::post('/stock/adjust', [StockController::class, 'adjust']);
Route::post('/stock/expired', [StockController::class, 'expired']);
Route::post('/stock/transfer', [StockController::class, 'transfer']);
Route::put('/stock/level', [StockController::class, 'setLowLevel']);

// Staff directory & chat
Route::get('/mobile/staff', [StaffChatController::class, 'directory']);
Route::get('/mobile/staff/{id}', [StaffChatController::class, 'profile'])->whereNumber('id');
Route::get('/mobile/chats', [StaffChatController::class, 'index']);
Route::get('/mobile/chats/unread-count', [StaffChatController::class, 'unreadCount']);
Route::post('/mobile/chats', [StaffChatController::class, 'open']);
Route::get('/mobile/chats/{id}/messages', [StaffChatController::class, 'messages'])->whereNumber('id');
Route::post('/mobile/chats/{id}/messages', [StaffChatController::class, 'send'])->whereNumber('id');
Route::post('/mobile/chats/{id}/read', [StaffChatController::class, 'read'])->whereNumber('id');

// Help: problem reports & crash reports
Route::post('/mobile/support-tickets', [MobileSupportController::class, 'report']);
Route::post('/mobile/error-reports', [MobileSupportController::class, 'errors']);
Route::get('/mobile-admin/tickets', [MobileSupportController::class, 'tickets']);
Route::post('/mobile-admin/tickets/{id}/resolve', [MobileSupportController::class, 'resolve'])->whereNumber('id');
Route::get('/mobile-admin/errors', [MobileSupportController::class, 'errorSummary']);



    // Stock Management (web)
    Route::get('/stock-admin/meta', [\App\Http\Controllers\Api\StockAdminController::class, 'meta']);
    Route::get('/stock-admin/matrix', [\App\Http\Controllers\Api\StockAdminController::class, 'matrix']);
    Route::post('/stock-admin/receive-bulk', [\App\Http\Controllers\Api\StockAdminController::class, 'receiveBulk']);
    Route::get('/stock-admin/movements', [\App\Http\Controllers\Api\StockAdminController::class, 'movements']);
    Route::get('/stock-admin/movements/export', [\App\Http\Controllers\Api\StockAdminController::class, 'export']);
    Route::get('/stock-admin/expiring', [\App\Http\Controllers\Api\StockAdminController::class, 'expiring']);
    Route::get('/stock-admin/items', [\App\Http\Controllers\Api\StockAdminController::class, 'items']);
    Route::post('/stock-admin/items', [\App\Http\Controllers\Api\StockAdminController::class, 'storeItem']);
    Route::put('/stock-admin/items/{id}', [\App\Http\Controllers\Api\StockAdminController::class, 'updateItem'])->whereNumber('id');
    

    Route::get('/mobile-admin/releases', [AppReleaseController::class, 'index']);
Route::post('/mobile-admin/releases', [AppReleaseController::class, 'store']);
Route::patch('/mobile-admin/releases/{id}', [AppReleaseController::class, 'update'])->whereNumber('id');
Route::delete('/mobile-admin/releases/{id}', [AppReleaseController::class, 'destroy'])->whereNumber('id');

Route::get('/mobile/profile/photo', [\App\Http\Controllers\Api\ProfilePhotoController::class, 'show']);
Route::post('/mobile/profile/photo', [\App\Http\Controllers\Api\ProfilePhotoController::class, 'store']);
Route::delete('/mobile/profile/photo', [\App\Http\Controllers\Api\ProfilePhotoController::class, 'destroy']);

Route::get('/mobile/app-promo', [\App\Http\Controllers\Api\MobileAppPromoController::class, 'forMe']);
Route::get('/mobile-admin/app-promo', [\App\Http\Controllers\Api\MobileAppPromoController::class, 'show']);
Route::put('/mobile-admin/app-promo', [\App\Http\Controllers\Api\MobileAppPromoController::class, 'update']);

});


Route::get('/mobile/app-version', [AppReleaseController::class, 'latest']);
Route::get('/mobile/app-latest', [AppReleaseController::class, 'latestApk']);   // always the newest APK — share this link
Route::get('/mobile-app/download/{id}/{token}', [AppReleaseController::class, 'download'])->whereNumber('id');

Route::get('/staff-photos/{file}', [\App\Http\Controllers\Api\ProfilePhotoController::class, 'file']);