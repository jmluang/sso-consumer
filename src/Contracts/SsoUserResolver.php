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
     *   1. For v2 tickets, look up the local admin user by $claims['phone'].
     *   2. Fall back to $claims['email'] only for legacy records or v1 tickets.
     *   3. Return null if phone and email resolve to different local users.
     *   4. Return null if not found (package will emit `user_not_found`).
     *   5. Update SSO-related fields (last_login_at, etc.).
     *   6. Call Auth::guard(...)->login($user).
     *   7. Call $request->session()->regenerate() to prevent fixation.
     *   8. Invoke any business-specific side effects (recordLogin, etc.).
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
     *     phone?: string,
     *     email?: string,
     *     name?: string,
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
