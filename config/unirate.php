<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | Your UniRate API key. Get a free one at https://unirateapi.com.
    |
    */

    'api_key' => env('UNIRATE_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Base URL
    |--------------------------------------------------------------------------
    |
    | Override for testing or self-hosted instances.
    |
    */

    'base_url' => env('UNIRATE_API_BASE_URL', 'https://api.unirateapi.com'),

    /*
    |--------------------------------------------------------------------------
    | HTTP timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | Kept short so a slow API can't stall page renders. On timeout the tags
    | fall back to empty output / {{ error }}.
    |
    */

    'timeout' => (int) env('UNIRATE_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Cache TTL (seconds)
    |--------------------------------------------------------------------------
    |
    | Successful API responses are cached in your default cache store for this
    | many seconds, so tags don't hit the API on every page render. Set to 0
    | to disable caching.
    |
    */

    'cache_ttl' => (int) env('UNIRATE_CACHE_TTL', 600),

];
