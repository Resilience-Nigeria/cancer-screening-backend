<?php

/*
|--------------------------------------------------------------------------
| Bloom mobile app API
|--------------------------------------------------------------------------
| Load from routes/api.php with this ONE line (do not paste this file's contents):
|
|     require __DIR__ . '/bloom.php';
|
| No `use` lines and every class is fully qualified, so this file can never clash
| with the imports at the top of routes/api.php (which has its own AuthController
| and Route). Every URL is /api/bloom/...
*/

$route = \Illuminate\Support\Facades\Route::class;

$route::prefix('bloom')->group(function () use ($route) {

    $auth      = \App\Http\Controllers\Api\Bloom\AuthController::class;
    $centre    = \App\Http\Controllers\Api\Bloom\CentreController::class;
    $profile   = \App\Http\Controllers\Api\Bloom\ProfileController::class;
    $appt      = \App\Http\Controllers\Api\Bloom\AppointmentController::class;
    $bloomOnly = \App\Http\Middleware\EnsureBloomAccount::class;

    // Sign-up, sign-in, password reset (public)
    $route::prefix('auth')->group(function () use ($route, $auth) {
        $route::post('register', [$auth, 'register'])->middleware('throttle:5,1');
        $route::post('verify',   [$auth, 'verify'])->middleware('throttle:10,1');
        $route::post('resend',   [$auth, 'resend'])->middleware('throttle:5,1');
        $route::post('login',    [$auth, 'login'])->middleware('throttle:10,1');
        $route::post('forgot',   [$auth, 'forgot'])->middleware('throttle:5,1');
        $route::post('reset',    [$auth, 'reset'])->middleware('throttle:10,1');
    });

    // Find centres (public, so people can look before signing up)
    $route::get('centres',      [$centre, 'index'])->middleware('throttle:60,1');
    $route::get('centres/{id}', [$centre, 'show'])->whereNumber('id')->middleware('throttle:60,1');

    // Signed-in Bloom account
    $route::middleware($bloomOnly)->group(function () use ($route, $auth, $profile, $appt) {
        $route::get('auth/me',      [$auth, 'me']);
        $route::post('auth/logout', [$auth, 'logout']);

        $route::patch('profile',         [$profile, 'update']);
        $route::post('profile/password', [$profile, 'changePassword'])->middleware('throttle:5,1');
        $route::delete('account',        [$profile, 'destroy'])->middleware('throttle:5,1');

        $route::get('appointments',              [$appt, 'index']);
        $route::post('appointments',             [$appt, 'store'])->middleware('throttle:20,1');
        $route::post('appointments/{id}/cancel', [$appt, 'cancel'])->whereNumber('id');
    });
});
