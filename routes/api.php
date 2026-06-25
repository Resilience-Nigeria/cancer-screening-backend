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

use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

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

    Route::get('/users/organizations', [UserController::class, 'getOrganizations']);
    
    Route::get('/clients', [ClientController::class, 'index']);
    Route::post('/clients', [ClientController::class, 'store']);
    Route::get('/clients/{client}', [ClientController::class, 'show']);
    Route::patch('/clients/{client}', [ClientController::class, 'update']);
    Route::get('/clients/search/details', [ClientController::class, 'search']);

    Route::get('/clients/{client}/risk-profile', [RiskProfileController::class, 'show']);
    Route::post('/clients/{client}/risk-profile', [RiskProfileController::class, 'upsert']);

    Route::get('/clients/{client}/visits', [ScreeningVisitController::class, 'index']);
    Route::post('/clients/{client}/visits', [ScreeningVisitController::class, 'store']);
    Route::get('/visits/{visit}', [ScreeningVisitController::class, 'show']);

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
    Route::get('/users/facilities', [UserController::class, 'facilities']);
    Route::get('/users/{user}', [UserController::class, 'show']);
    Route::post('/users', [UserController::class, 'store']);
    Route::put('/users/{user}', [UserController::class, 'update']);
    Route::delete('/users/{user}', [UserController::class, 'destroy']);


    // Cancer Analytics Routes

    Route::get('/analytics/cancer-by-age', [App\Http\Controllers\CancerAnalyticsController::class, 'getCancerByAgeClassification']);
    Route::get('/analytics/cancer-type-age-distribution/{cancerType}', [App\Http\Controllers\CancerAnalyticsController::class, 'getCancerTypeAgeDistribution']);
    Route::get('/analytics/gender-age-comparison', [App\Http\Controllers\CancerAnalyticsController::class, 'getGenderAgeComparison']);


});