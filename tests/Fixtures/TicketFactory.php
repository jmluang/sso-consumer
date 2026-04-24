<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Tests\Fixtures;

use Firebase\JWT\JWT;

class TicketFactory
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array{0: string, 1: array<string, mixed>}
     */
    public static function valid(array $overrides = []): array
    {
        $claims = array_merge(self::validClaims(), $overrides);

        return [
            self::encode($claims),
            $claims,
        ];
    }

    public static function expired(): string
    {
        return self::valid([
            'iat' => time() - 300,
            'exp' => time() - 120,
        ])[0];
    }

    public static function wrongAlg(): string
    {
        return JWT::encode(self::validClaims(), 'not-a-production-secret', 'HS256', 'portal-2026-04');
    }

    public static function badSignature(): string
    {
        $ticket = self::valid()[0];
        $segments = explode('.', $ticket);

        $segments[2] = ($segments[2][0] === 'A' ? 'B' : 'A').substr($segments[2], 1);

        return implode('.', $segments);
    }

    public static function wrongVersion(int $v): string
    {
        return self::valid(['v' => $v])[0];
    }

    public static function wrongIssuer(): string
    {
        return self::valid(['iss' => 'wrong-issuer'])[0];
    }

    public static function wrongAudience(string $aud): string
    {
        return self::valid(['aud' => $aud])[0];
    }

    public static function wrongTenantDomain(string $domain): string
    {
        return self::valid(['tenant_domain' => $domain])[0];
    }

    public static function unsignedMalformed(): string
    {
        return 'not-a-valid-jwt';
    }

    /**
     * @return array<string, mixed>
     */
    private static function validClaims(): array
    {
        $now = time();

        return [
            'iss' => 'sso-portal',
            'aud' => 'xiaohongshu',
            'sub' => 'alice@florentiavillage.com',
            'email' => 'alice@florentiavillage.com',
            'tenant_domain' => 'shanghai.florentiavillage.com',
            'tenant_id' => 17,
            'tenant_system' => 'xiaohongshu',
            'jti' => bin2hex(random_bytes(16)),
            'v' => 1,
            'iat' => $now,
            'exp' => $now + 120,
        ];
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private static function encode(array $claims): string
    {
        return JWT::encode($claims, self::privateKey(), 'RS256', 'portal-2026-04');
    }

    private static function privateKey(): string
    {
        $key = file_get_contents(__DIR__.'/keys/test-private.pem');

        if ($key === false) {
            throw new \RuntimeException('Unable to read test private key.');
        }

        return $key;
    }
}
