<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Portal
    |--------------------------------------------------------------------------
    | Portal root URL, used to render the "return to portal" button on error
    | pages and to build the SSO login button href.
    */
    'portal_url' => env('SSO_PORTAL_URL'),

    /*
    |--------------------------------------------------------------------------
    | System Code
    |--------------------------------------------------------------------------
    | This application's system code. Must match tenant_registry.system_code
    | on the portal; verified against the JWT `aud` claim.
    */
    'system_code' => env('SSO_SYSTEM_CODE'),

    /*
    |--------------------------------------------------------------------------
    | Portal Public Key
    |--------------------------------------------------------------------------
    | RS256 PEM public key used to verify ticket signatures. Prefer storing
    | the PEM file on disk and loading it here; fall back to env string.
    */
    'public_key' => env('SSO_PORTAL_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | JWT Contract
    |--------------------------------------------------------------------------
    */
    'algorithm' => 'RS256',
    'issuer' => 'sso-portal',
    'supported_versions' => [1],
    'leeway_seconds' => 5,

    /*
    |--------------------------------------------------------------------------
    | Route
    |--------------------------------------------------------------------------
    | Consume endpoint path and middleware. Must be under a middleware group
    | that resolves the current tenant (e.g. Spatie Multitenancy).
    */
    'consume_path' => '/admin-app/sso/consume',
    'consume_middleware' => ['web', 'tenant'],

    /*
    |--------------------------------------------------------------------------
    | Resolver
    |--------------------------------------------------------------------------
    | Class implementing \Jmluang\SsoConsumer\Contracts\SsoUserResolver. This
    | class is responsible for finding the local admin user by email and
    | calling Auth::guard(...)->login($user).
    */
    'resolver' => null,

    /*
    |--------------------------------------------------------------------------
    | Redirects
    |--------------------------------------------------------------------------
    */
    'success_redirect' => '/admin-app/dashboard',
    'failure_redirect' => '/admin-app/login',

    /*
    |--------------------------------------------------------------------------
    | Replay Cache
    |--------------------------------------------------------------------------
    | Store used for jti one-time-use tracking. Null = use cache.default.
    | In multi-instance environments this MUST be a shared store (redis).
    */
    'replay_cache_store' => null,
    'replay_cache_prefix' => 'sso_consumer:jti:',

    /*
    |--------------------------------------------------------------------------
    | Error View
    |--------------------------------------------------------------------------
    */
    'error_view' => 'sso-consumer::error',
];
