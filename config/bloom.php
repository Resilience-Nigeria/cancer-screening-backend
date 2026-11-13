<?php

/*
|--------------------------------------------------------------------------
| Bloom mobile app (breast health) settings
|--------------------------------------------------------------------------
| Everything is read here with env() so it keeps working after
| `php artisan config:cache`. Defaults are safe for production.
*/

return [

    // How long a Bloom login token lives. The app signs the person out when it
    // expires. This does NOT change the staff dashboard's JWT_TTL.
    'token_ttl_days' => (int) env('BLOOM_TOKEN_TTL_DAYS', 30),

    // Brevo email (the one-time codes). Read here so it survives `php artisan config:cache`.
    'mail' => [
        'api_key'      => env('BREVO_API_KEY'),
        'from_address' => env('MAIL_FROM_ADDRESS'),
        'from_name'    => env('MAIL_FROM_NAME', 'Bloom'),
    ],

    'otp' => [
        'length'          => 6,
        'ttl_minutes'     => (int) env('BLOOM_OTP_TTL_MINUTES', 10),
        'max_attempts'    => (int) env('BLOOM_OTP_MAX_ATTEMPTS', 5),
        'resend_after'    => (int) env('BLOOM_OTP_RESEND_SECONDS', 60),
        // DEV ONLY. When true the code is written to the log and returned in the
        // API response as "debug_code", and a failed SMS no longer blocks sign-up.
        // Never enable this in production.
        'debug'           => (bool) env('BLOOM_OTP_DEBUG', false),
    ],

    'centres' => [
        // "screening" => isScreeningCenter facilities, "treatment" => isTreatmentCenter,
        // "all" => either. The app asks for screening centres.
        'default_type'     => env('BLOOM_CENTRES_DEFAULT_TYPE', 'screening'),
        'default_radius_km' => (int) env('BLOOM_CENTRES_RADIUS_KM', 150),
        'max_limit'        => 200,
        'include_services' => (bool) env('BLOOM_CENTRES_INCLUDE_SERVICES', true),
        // The facility<->service pivot table; the first one that exists is used.
        'pivot_tables'     => ['facility_services', 'facilityServices', 'facilityservices'],
        'timezone'         => env('BLOOM_TIMEZONE', 'Africa/Lagos'),
    ],

    'appointments' => [
        // Stops one person flooding a centre with requests.
        'max_pending'   => (int) env('BLOOM_APPT_MAX_PENDING', 5),
        'max_days_ahead' => 90,
        'types'         => ['general', 'mammogram', 'cbe', 'ultrasound', 'biopsy', 'mri'],
    ],

    'push' => [
        // Only needed if "Enhanced push security" is turned on for the Expo project (expo.dev > project > settings).
        'expo_access_token' => env('BLOOM_EXPO_ACCESS_TOKEN'),
    ],
];
