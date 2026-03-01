<?php

declare(strict_types=1);

use Deplox\Shield\Shield;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains will receive stateful API
    | authentication cookies. Typically, these should include your local
    | and production domains which access your API via a frontend SPA.
    |
    | The application URL (from APP_URL) is automatically included.
    |
    */

    'stateful' => explode(',', env('SHIELD_STATEFUL_DOMAINS', implode(',', [
        'localhost',
        'localhost:3000',
        '127.0.0.1',
        '127.0.0.1:8000',
        '::1',
        Shield::currentApplicationUrlWithPort(),
    ]))),

    /*
    |--------------------------------------------------------------------------
    | Stateful Subdomains
    |--------------------------------------------------------------------------
    |
    | When enabled, subdomains of the configured stateful domains will also
    | be treated as stateful (e.g., "*.example.com" for "example.com").
    |
    */

    'stateful_subdomains' => false,

];
