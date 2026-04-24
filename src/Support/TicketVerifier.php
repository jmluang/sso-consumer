<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Support;

use Firebase\JWT\ExpiredException as FirebaseExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Log;
use Jmluang\SsoConsumer\Exceptions\AudienceMismatchException;
use Jmluang\SsoConsumer\Exceptions\ExpiredTicketException;
use Jmluang\SsoConsumer\Exceptions\InvalidTicketException;
use Jmluang\SsoConsumer\Exceptions\TenantMismatchException;
use Jmluang\SsoConsumer\Exceptions\UnsupportedVersionException;
use Throwable;

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
        $header = $this->decodeHeader($ticket);

        if (($header['alg'] ?? null) !== 'RS256') {
            throw new InvalidTicketException;
        }

        if (isset($header['kid'])) {
            Log::debug('Verifying SSO ticket with key id.', ['kid' => $header['kid']]);
        }

        $previousLeeway = JWT::$leeway;
        JWT::$leeway = (int) config('sso-consumer.leeway_seconds', 5);

        try {
            $payload = JWT::decode(
                $ticket,
                new Key((string) config('sso-consumer.public_key'), 'RS256')
            );
        } catch (FirebaseExpiredException $e) {
            throw new ExpiredTicketException(previous: $e);
        } catch (Throwable $e) {
            throw new InvalidTicketException(previous: $e);
        } finally {
            JWT::$leeway = $previousLeeway;
        }

        $claims = $this->payloadToArray($payload);

        if (! in_array($claims['v'] ?? null, (array) config('sso-consumer.supported_versions', [1]), true)) {
            throw new UnsupportedVersionException;
        }

        if (($claims['iss'] ?? null) !== config('sso-consumer.issuer')) {
            throw new InvalidTicketException;
        }

        if (($claims['aud'] ?? null) !== config('sso-consumer.system_code')) {
            throw new AudienceMismatchException;
        }

        if (($claims['tenant_domain'] ?? null) !== $requestHost) {
            throw new TenantMismatchException;
        }

        return $claims;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeHeader(string $ticket): array
    {
        $segments = explode('.', $ticket);

        if (count($segments) !== 3) {
            throw new InvalidTicketException;
        }

        try {
            $header = JWT::jsonDecode(JWT::urlsafeB64Decode($segments[0]));
        } catch (Throwable $e) {
            throw new InvalidTicketException(previous: $e);
        }

        if (! is_object($header)) {
            throw new InvalidTicketException;
        }

        return $this->payloadToArray($header);
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadToArray(object $payload): array
    {
        /** @var array<string, mixed> $claims */
        $claims = json_decode((string) json_encode($payload, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        return $claims;
    }
}
