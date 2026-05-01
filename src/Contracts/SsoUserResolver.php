<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

/**
 * Look up a local admin user from verified JWT claims and sign them in.
 *
 * The library calls these methods only AFTER all JWT-layer checks have
 * passed (signature, version, expiry, audience, tenant_domain, jti replay).
 * The library is responsible for orchestration:
 *
 *   1. If the ticket carries a non-empty `phone` claim, call `findByPhone`.
 *   2. If the ticket carries a non-empty `email` claim, call `findByEmail`.
 *   3. If both lookups return a user and the users differ, the library
 *      raises `IdentityConflictException` — `login()` is never called.
 *   4. Otherwise the library passes the resolved user to `login()`.
 *
 * Implementations MUST NOT:
 *   - Re-verify JWT signature / claims.
 *   - Re-validate tenant_domain.
 *   - Mix the phone-vs-email conflict check into a single lookup; that
 *     job belongs to the library.
 *
 * @phpstan-type TicketClaims array{
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
 * }
 */
interface SsoUserResolver
{
    /**
     * Look up a local user matching the verified phone claim.
     *
     * Return null when no user matches. The library calls this only when
     * the ticket actually carries a non-empty phone claim.
     *
     * @param  TicketClaims  $claims
     */
    public function findByPhone(string $phone, array $claims, Request $request): ?Authenticatable;

    /**
     * Look up a local user matching the verified email claim.
     *
     * Return null when no user matches. The library calls this only when
     * the ticket actually carries a non-empty email claim (e.g. v1 tickets,
     * or v2 tickets that include an optional email).
     *
     * @param  TicketClaims  $claims
     */
    public function findByEmail(string $email, array $claims, Request $request): ?Authenticatable;

    /**
     * Sign the resolved user into the host application.
     *
     * Called once per successful consume after the library has confirmed
     * that any phone/email claims in the ticket point to the same local
     * user. Typical responsibilities:
     *   - `Auth::guard(...)->login($user)`
     *   - `$request->session()->regenerate()` (prevent fixation)
     *   - Update SSO-related fields (last_login_at, etc.)
     *   - Invoke business-specific side effects.
     *
     * @param  TicketClaims  $claims
     */
    public function login(Authenticatable $user, array $claims, Request $request): void;
}
