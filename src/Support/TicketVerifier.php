<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Support;

class TicketVerifier
{
    /**
     * Verify a portal-signed JWT ticket and return its claims.
     *
     * Checks (in this order — see docs/sso/contracts/jwt-claims-v1.md §验证规则):
     *   1. Parse structure        → InvalidTicketException
     *   2. alg == RS256           → InvalidTicketException
     *   3. Signature via public_key → InvalidTicketException
     *   4. v in supported_versions → UnsupportedVersionException
     *   5. iss == config issuer    → InvalidTicketException
     *   6. exp > now (leeway)      → ExpiredTicketException
     *   7. iat <= now + leeway     → InvalidTicketException
     *   8. nbf <= now + leeway     → InvalidTicketException (if present)
     *   9. aud == system_code      → AudienceMismatchException
     *  10. tenant_domain == $host  → TenantMismatchException
     *
     * Replay check (jti) is done by JtiReplayGuard, not here.
     *
     * TODO(OpenCode): implement using firebase/php-jwt.
     *
     * @return array<string, mixed>
     */
    public function verify(string $ticket, string $requestHost): array
    {
        throw new \LogicException('TicketVerifier::verify not implemented yet.');
    }
}
