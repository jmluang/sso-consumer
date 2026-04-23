<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

interface SsoUserResolver
{
    /**
     * Resolve a local admin user from verified JWT claims.
     *
     * Called by the package only AFTER all JWT-layer checks have passed
     * (signature, version, expiry, audience, tenant_domain, jti replay).
     *
     * Implementation responsibilities:
     *   1. Look up the local admin user by $claims['email'].
     *   2. Return null if not found (package will emit `user_not_found`).
     *   3. Update SSO-related fields (last_login_at, etc.).
     *   4. Call Auth::guard(...)->login($user).
     *   5. Call $request->session()->regenerate() to prevent fixation.
     *   6. Invoke any business-specific side effects (recordLogin, etc.).
     *
     * Implementation MUST NOT:
     *   - Re-verify JWT signature / claims.
     *   - Re-validate tenant_domain.
     *   - Throw uncaught exceptions (those become `resolver_failed`).
     *
     * @param  array{
     *     iss: string,
     *     aud: string,
     *     sub: string,
     *     email: string,
     *     tenant_domain: string,
     *     tenant_id: int,
     *     tenant_system: string,
     *     jti: string,
     *     v: int,
     *     iat: int,
     *     exp: int
     * }  $claims
     */
    public function resolve(array $claims, Request $request): ?Authenticatable;
}
