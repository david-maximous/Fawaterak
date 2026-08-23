<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fawaterak base URL
    |--------------------------------------------------------------------------
    |
    | https://staging.fawaterk.com/  -> staging / testing
    | https://app.fawaterk.com/      -> production
    |
    */
    'FAWATERAK_URL' => env('FAWATERAK_URL', "https://staging.fawaterk.com/"),

    /*
    |--------------------------------------------------------------------------
    | Vendor API key (legacy key)
    |--------------------------------------------------------------------------
    |
    | Still required even when OAuth is configured. It is used to:
    |   - sign / verify every webhook hash (paid, failed, cancel, refund, token)
    |   - authenticate the v2 tokenization & recurring endpoints
    |
    | Vendor dashboard -> Integrations -> API key
    |
    */
    'FAWATERAK_API_KEY' => env('FAWATERAK_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | OAuth client credentials (API v3)
    |--------------------------------------------------------------------------
    |
    | Required for every /api/v3/* call (transactions, payment methods, refunds).
    | Vendor dashboard -> Integrations -> OAuth client credentials
    |
    */
    'FAWATERAK_CLIENT_ID' => env('FAWATERAK_CLIENT_ID'),
    'FAWATERAK_CLIENT_SECRET' => env('FAWATERAK_CLIENT_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Redirection URLs
    |--------------------------------------------------------------------------
    |
    | Legacy behaviour (kept for backwards compatibility): a single route name
    | to which "/success", "/failed" and "/pending" are appended.
    |
    */
    'FAWATERAK_REDIRECT_URL' => "payment-redirect",

    /*
    | Explicit URLs. Each value may be a route name or an absolute URL.
    | These take precedence over FAWATERAK_REDIRECT_URL and may be overridden
    | per transaction with setSuccessUrl() / setFailUrl() / ... setters.
    */
    'FAWATERAK_SUCCESS_URL' => env('FAWATERAK_SUCCESS_URL'),
    'FAWATERAK_FAIL_URL' => env('FAWATERAK_FAIL_URL'),
    'FAWATERAK_PENDING_URL' => env('FAWATERAK_PENDING_URL'),
    'FAWATERAK_BACK_URL' => env('FAWATERAK_BACK_URL'),

    /*
    | Server to server webhook URL for the paid / pending notification.
    | Add "_json" to the path to receive a JSON body instead of form-encoded.
    */
    'FAWATERAK_WEBHOOK_URL' => env('FAWATERAK_WEBHOOK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Access token cache
    |--------------------------------------------------------------------------
    |
    | The OAuth access token is fetched once and cached until it expires.
    | Leave FAWATERAK_CACHE_STORE null to use the application default store.
    |
    */
    'FAWATERAK_CACHE_STORE' => env('FAWATERAK_CACHE_STORE'),
    'FAWATERAK_CACHE_PREFIX' => 'fawaterak_token_',

    /*
    |--------------------------------------------------------------------------
    | Misc
    |--------------------------------------------------------------------------
    */
    'FAWATERAK_TIMEOUT' => env('FAWATERAK_TIMEOUT', 30),
    'FAWATERAK_LANG' => env('FAWATERAK_LANG', 'en'),

    /*
    | How long (in seconds) the payment methods list is cached. 0 disables it.
    */
    'FAWATERAK_METHODS_CACHE_TTL' => env('FAWATERAK_METHODS_CACHE_TTL', 600),

    'APP_NAME' => env('APP_NAME'),
];
