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
    Route::get('/clients', [ClientController::class, 'index']);
    Route::post('/clients', [ClientController::class, 'store']);
    Route::get('/clients/{client}', [ClientController::class, 'show']);
    Route::patch('/clients/{client}', [ClientController::class, 'update']);

    Route::get('/clients/{client}/risk-profile', [RiskProfileController::class, 'show']);
    Route::put('/clients/{client}/risk-profile', [RiskProfileController::class, 'upsert']);

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

    Route::get('/visits', [ScreeningVisitController::class, 'indexAll']);
});