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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'dintero' => [
        'api_url' => env('DINTERO_API_URL', 'https://api.dintero.com'),
        'partner_account_id' => env('DINTERO_PARTNER_ACCOUNT_ID'),
        'client_id' => env('DINTERO_CLIENT_ID'),
        'client_secret' => env('DINTERO_CLIENT_SECRET'),
        'onboarding_url' => env('DINTERO_ONBOARDING_URL', 'https://onboarding.dintero.com'),
    ],

    /*
    | Platform Converge2 (Elavon) credentials for enterprise API hosted subscription
    | (GET /api/enterprise/{uid}/start). Use sandbox HPP when sandbox is true.
    |
    | Shop / plugin platform subscriptions (is_demo on shops and payment_method_accesses)
    | use credentials.production vs credentials.sandbox based on the record flag —
    | not the merchant's own Elavon keys.
    */
    'enterprise_elavon' => [
        'credentials' => [
            'production' => [
                'merchant_alias' => env('ELAVON_ENTERPRISE_MERCHANT_ALIAS'),
                'public_key' => env('ELAVON_ENTERPRISE_PUBLIC_KEY'),
                'secret_key' => env('ELAVON_ENTERPRISE_SECRET_KEY'),
            ],
            'sandbox' => [
                'merchant_alias' => env('ELAVON_ENTERPRISE_SANDBOX_MERCHANT_ALIAS'),
                'public_key' => env('ELAVON_ENTERPRISE_SANDBOX_PUBLIC_KEY'),
                'secret_key' => env('ELAVON_ENTERPRISE_SANDBOX_SECRET_KEY'),
            ],
        ],
        'merchant_alias' => env('ELAVON_ENTERPRISE_MERCHANT_ALIAS'),
        'public_key' => env('ELAVON_ENTERPRISE_PUBLIC_KEY'),
        'secret_key' => env('ELAVON_ENTERPRISE_SECRET_KEY'),
        'sandbox' => env('ELAVON_ENTERPRISE_SANDBOX') !== null
            ? filter_var(env('ELAVON_ENTERPRISE_SANDBOX'), FILTER_VALIDATE_BOOLEAN)
            : env('APP_ENV') === 'local',
        'disable_hpp_3ds' => filter_var(env('ELAVON_HPP_DISABLE_3DS', false), FILTER_VALIDATE_BOOLEAN),
        'charge_on_server' => filter_var(env('ELAVON_HPP_CHARGE_ON_SERVER', false), FILTER_VALIDATE_BOOLEAN),
        'hpp_origin_url' => env('ELAVON_HPP_ORIGIN_URL'),
    ],

    'surfboard' => [
        'api_url' => env('SURFBOARD_API_URL', 'https://lithium.surfgw.com/api'),
        'api_key' => env('SURFBOARD_API_KEY'),
        'api_secret' => env('SURFBOARD_API_SECRET'),
        'partner_id' => env('SURFBOARD_PARTNER_ID'),
        'merchant_id' => env('SURFBOARD_MERCHANT_ID'),
        'store_id' => env('SURFBOARD_STORE_ID'),
    ],

];
