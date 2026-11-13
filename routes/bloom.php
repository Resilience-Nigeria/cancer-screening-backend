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

    // Phone notifications and articles (public: signed-out phones get articles/announcements too)
    $push  = \App\Http\Controllers\Api\Bloom\PushTokenController::class;
    $posts = \App\Http\Controllers\Api\Bloom\PostController::class;
    $route::post('push-token',   [$push, 'registerWoman'])->middleware('throttle:20,1');
    $route::delete('push-token', [$push, 'unregister'])->middleware('throttle:20,1');
    $route::post('push-test',    [$push, 'test'])->middleware('throttle:5,1');
    $route::get('posts',         [$posts, 'womenIndex'])->middleware('throttle:60,1');
    $route::get('posts/{id}',    [$posts, 'womenShow'])->whereNumber('id')->middleware('throttle:60,1');

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
        $route::patch('appointments/{id}',       [$appt, 'reschedule'])->whereNumber('id')->middleware('throttle:20,1');
    });
});

/*
| Staff side: confirm / decline / complete the bookings clients make in the app.
| Same middleware as the other staff routes; a facility user only sees their own
| facility's bookings, national users see all. URLs are /api/bloom-admin/...
*/
$route::prefix('bloom-admin')->middleware(['auth:api', 'facility.scope'])->group(function () use ($route) {
    $admin = \App\Http\Controllers\Api\Bloom\AdminAppointmentController::class;

    $route::get('appointments',                  [$admin, 'index']);
    $route::post('appointments/{id}/confirm',    [$admin, 'confirm'])->whereNumber('id');
    $route::post('appointments/{id}/decline',    [$admin, 'decline'])->whereNumber('id');
    $route::post('appointments/{id}/complete',   [$admin, 'complete'])->whereNumber('id');
});

/*
| Community health workers (NCSR users with the NAVIGATOR role).
| Sign-in is the normal POST /api/auth/login; these are sign-up and their cases.
*/
$route::prefix('bloom-chw')->group(function () use ($route) {
    $auth  = \App\Http\Controllers\Api\Bloom\NavigatorAuthController::class;
    $cases = \App\Http\Controllers\Api\Bloom\NavigatorCaseController::class;

    $route::get('facilities',      [$auth, 'facilities'])->middleware('throttle:60,1');
    $route::post('register',       [$auth, 'register'])->middleware('throttle:5,1');
    $route::post('account-status', [$auth, 'status'])->middleware('throttle:10,1');

    $route::middleware('auth:api')->group(function () use ($route, $cases) {
        $route::get('cases',                  [$cases, 'index']);
        $route::get('cases/{id}',             [$cases, 'show'])->whereNumber('id');
        $route::post('cases/{id}/start',      [$cases, 'start'])->whereNumber('id');
        $route::patch('cases/{id}/notes',     [$cases, 'notes'])->whereNumber('id');
        $route::post('cases/{id}/community',  [$cases, 'community'])->whereNumber('id');
        $route::post('cases/{id}/phc',        [$cases, 'phc'])->whereNumber('id');
        $route::post('cases/{id}/referral',   [$cases, 'referral'])->whereNumber('id');
        $route::post('cases/{id}/followup',   [$cases, 'followup'])->whereNumber('id');

        // Breast screening: NCSR client + samples/results (saved in NCSR and copied to Bloom).
        $scr = \App\Http\Controllers\Api\Bloom\NavigatorScreeningController::class;
        $route::get('screening-tests',            [$scr, 'tests']);
        $route::get('cases/{id}/screening',       [$scr, 'show'])->whereNumber('id');
        $route::post('cases/{id}/ncsr-client',    [$scr, 'registerClient'])->whereNumber('id');
        $route::post('cases/{id}/samples',        [$scr, 'storeSample'])->whereNumber('id');
        $route::patch('samples/{sampleId}/result', [$scr, 'recordResult'])->whereNumber('sampleId');

        // Notification bell; find clients at her facility and ask an admin for access.
        $acc = \App\Http\Controllers\Api\Bloom\NavigatorAccessController::class;
        $route::get('notifications',       [$acc, 'notifications']);
        $route::post('notifications/read', [$acc, 'markRead']);
        $route::get('clients/search',      [$acc, 'search'])->middleware('throttle:60,1');
        $route::get('access-requests',     [$acc, 'index']);
        $route::post('access-requests',    [$acc, 'store'])->middleware('throttle:20,1');

        // Facility directory with contact details.
        $route::post('push-token',   [\App\Http\Controllers\Api\Bloom\PushTokenController::class, 'registerChw']);
        $route::delete('push-token', [\App\Http\Controllers\Api\Bloom\PushTokenController::class, 'unregister']);
        $route::get('posts',         [\App\Http\Controllers\Api\Bloom\PostController::class, 'chwIndex']);
        $route::get('posts/{id}',    [\App\Http\Controllers\Api\Bloom\PostController::class, 'chwShow'])->whereNumber('id');

        $route::get('facility-directory', [\App\Http\Controllers\Api\Bloom\NavigatorFacilityController::class, 'index']);
    });
});

/* Admin: verify health workers and assign cases (super admin, NICRAT admin, hospital admin; facility-scoped). */
$route::prefix('bloom-admin')->middleware(['auth:api', 'facility.scope'])->group(function () use ($route) {
    $cases = \App\Http\Controllers\Api\Bloom\AdminCaseController::class;
    $navs  = \App\Http\Controllers\Api\Bloom\AdminNavigatorController::class;

    $route::get('cases',                      [$cases, 'index']);
    $route::get('cases/navigators',           [$cases, 'navigators']);
    $route::post('cases/{id}/assign',         [$cases, 'assign'])->whereNumber('id');
    $route::get('tests',                      [\App\Http\Controllers\Api\Bloom\AdminTestsController::class, 'index']);
    $route::get('navigators',                 [$navs, 'index']);
    $route::post('navigators/{id}/approve',   [$navs, 'approve'])->whereNumber('id');
    $route::post('navigators/{id}/reject',    [$navs, 'reject'])->whereNumber('id');

    // Articles and announcements for the app (NICRAT admins)
    $ap = \App\Http\Controllers\Api\Bloom\AdminPostController::class;
    $route::get('posts',                  [$ap, 'index']);
    $route::post('posts',                 [$ap, 'store']);
    $route::post('posts/{id}',            [$ap, 'update'])->whereNumber('id');
    $route::post('posts/{id}/publish',    [$ap, 'publish'])->whereNumber('id');
    $route::post('posts/{id}/notify',     [$ap, 'notify'])->whereNumber('id')->middleware('throttle:10,1');
    $route::post('posts/{id}/unpublish',  [$ap, 'unpublish'])->whereNumber('id');
    $route::delete('posts/{id}',          [$ap, 'destroy'])->whereNumber('id');

    $access = \App\Http\Controllers\Api\Bloom\AdminAccessController::class;
    $route::get('access-requests',                [$access, 'index']);
    $route::post('access-requests/{id}/approve',  [$access, 'approve'])->whereNumber('id');
    $route::post('access-requests/{id}/reject',   [$access, 'reject'])->whereNumber('id');
});
