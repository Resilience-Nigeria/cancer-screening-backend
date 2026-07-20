<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'whatsapp' => [
    'api_url'         => env('WHATSAPP_API_URL', 'https://graph.facebook.com/v19.0'),
    'token'           => env('WHATSAPP_TOKEN'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
],

'brevo' => [
        'key'        => env('BREVO_API_KEY'),
        'from_email' => env('BREVO_FROM_EMAIL', 'noreply@ncsr.gov.ng'),
        'from_name'  => env('BREVO_FROM_NAME', 'NCSR — NICRAT'),
        'sms_sender' => env('BREVO_SMS_SENDER', 'NCSR'),
    ],

    'bulksms_nigeria' => [
        'api_token' => env('BULKSMS_NIGERIA_API_TOKEN'),
        'sender_id' => env('BULKSMS_NIGERIA_SENDER_ID', 'NCSR'),
        // Set to true to hit the sandbox endpoint instead (simulated sends, no wallet deduction).
        'sandbox'   => env('BULKSMS_NIGERIA_SANDBOX', false),
    ],


        'twilio' => [
    'sid'        => env('TWILIO_SID'),
    'auth_token' => env('TWILIO_AUTH_TOKEN'),
    'from'       => env('TWILIO_FROM'),
    'enabled'    => env('TWILIO_SMS_ENABLED', true),
    'whatsapp_from'  => env('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886'),
    'whatsapp_enabled' => env('TWILIO_WHATSAPP_ENABLED', true),
],

];
