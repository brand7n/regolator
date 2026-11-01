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
        'token' => env('POSTMARK_TOKEN'),
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

    'google' => [
        'calendar_api_key' => env('GOOGLE_CALENDAR_API_KEY'),
        'calendar_id' => env('GOOGLE_CALENDAR_ID'),
        'kennel' => env('GOOGLE_CALENDAR_KENNEL', 'NVHHH'),
        // Imported events store wall-clock time from this zone (see ImportStrava).
        'timezone' => env('GOOGLE_CALENDAR_TIMEZONE', 'America/New_York'),
    ],

    // Description -> structured trail facts (app:extract-event-details).
    'llm' => [
        'driver' => env('LLM_DRIVER', 'openai'),
        'api_key' => env('LLM_API_KEY'),
        'api_url' => env('LLM_API_URL'),
        'model' => env('LLM_MODEL'),
        'max_tokens' => env('LLM_MAX_TOKENS'),
    ],

    'strava' => [
        'client_id' => env('STRAVA_CLIENT_ID'),
        'client_secret' => env('STRAVA_CLIENT_SECRET'),
        'refresh_token' => env('STRAVA_REFRESH_TOKEN'),
    ],

    'paypal' => [
        'client_id' => env('PAYPAL_CLIENT_ID', ''),
        'client_secret' => env('PAYPAL_CLIENT_SECRET', ''),
        'sandbox' => env('PAYPAL_SANDBOX', false) ? 'sandbox.' : '',
    ],

];
