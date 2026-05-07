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
     * Checks (in this order):
     *   1. Parse structure         → InvalidTicketException
     *   2. alg == RS256            → InvalidTicketException
     *   3. Signature via public_key → InvalidTicketException
     *   4. Required claim shape     → InvalidTicketException
     *   5. v in supported_versions → UnsupportedVersionException
     *   6. iss == config issuer     → InvalidTicketException
     *   7. exp > now (leeway)       → ExpiredTicketException
     *   8. iat <= now + leeway      → InvalidTicketException
     *   9. nbf <= now + leeway      → InvalidTicketException (if present)
     *  10. aud == system_code       → AudienceMismatchException
     *  11. tenant_domain is an expected host → TenantMismatchException
     *
     * Replay check (jti) is done by JtiReplayGuard, not here.
     *
     * @param  string|array<int, string>  $expectedHost
     * @return array<string, mixed>
     */
    public function verify(string $ticket, string|array $expectedHost): array
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

        $this->validateRequiredClaims($claims);

        if (! in_array($claims['v'] ?? null, (array) config('sso-consumer.supported_versions', [1]), true)) {
            throw new UnsupportedVersionException;
        }

        $this->validateClaimShape($claims);

        if (($claims['iss'] ?? null) !== config('sso-consumer.issuer')) {
            throw new InvalidTicketException;
        }

        if (($claims['aud'] ?? null) !== config('sso-consumer.system_code')) {
            throw new AudienceMismatchException;
        }

        if (! in_array($claims['tenant_domain'] ?? null, $this->normalizeExpectedHosts($expectedHost), true)) {
            throw new TenantMismatchException;
        }

        return $claims;
    }

    /**
     * @param  string|array<int, string>  $expectedHost
     * @return array<int, string>
     */
    private function normalizeExpectedHosts(string|array $expectedHost): array
    {
        if (is_string($expectedHost)) {
            $expectedHost = [$expectedHost];
        }

        return array_values(array_filter(
            array_map(
                static fn (string $host): string => trim($host),
                $expectedHost
            ),
            static fn (string $host): bool => $host !== ''
        ));
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function validateRequiredClaims(array $claims): void
    {
        foreach (['iss', 'aud', 'sub', 'tenant_domain', 'tenant_system', 'jti'] as $claim) {
            if (! isset($claims[$claim]) || ! is_string($claims[$claim]) || trim($claims[$claim]) === '') {
                throw new InvalidTicketException;
            }
        }

        foreach (['tenant_id', 'v', 'iat', 'exp'] as $claim) {
            if (! isset($claims[$claim]) || ! is_int($claims[$claim])) {
                throw new InvalidTicketException;
            }
        }

        if (isset($claims['nbf']) && ! is_int($claims['nbf'])) {
            throw new InvalidTicketException;
        }

        if (! preg_match('/\A[a-f0-9]{32}\z/', $claims['jti'])) {
            throw new InvalidTicketException;
        }

        if ($claims['tenant_system'] !== $claims['aud']) {
            throw new InvalidTicketException;
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function validateClaimShape(array $claims): void
    {
        if (($claims['v'] ?? null) === 2) {
            if (! isset($claims['phone']) || ! is_string($claims['phone']) || trim($claims['phone']) === '') {
                throw new InvalidTicketException;
            }

            if (isset($claims['email']) && ! is_string($claims['email'])) {
                throw new InvalidTicketException;
            }

            if (isset($claims['name']) && ! is_string($claims['name'])) {
                throw new InvalidTicketException;
            }

            if ($claims['sub'] !== $claims['phone']) {
                throw new InvalidTicketException;
            }

            return;
        }

        if (($claims['v'] ?? null) === 1
            && (! isset($claims['email']) || ! is_string($claims['email']) || trim($claims['email']) === '')) {
            throw new InvalidTicketException;
        }

        if (($claims['v'] ?? null) === 1 && $claims['sub'] !== $claims['email']) {
            throw new InvalidTicketException;
        }
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
