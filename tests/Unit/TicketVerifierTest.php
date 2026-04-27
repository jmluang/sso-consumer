<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Tests\Unit;

use Jmluang\SsoConsumer\Exceptions\AudienceMismatchException;
use Jmluang\SsoConsumer\Exceptions\ExpiredTicketException;
use Jmluang\SsoConsumer\Exceptions\InvalidTicketException;
use Jmluang\SsoConsumer\Exceptions\TenantMismatchException;
use Jmluang\SsoConsumer\Exceptions\UnsupportedVersionException;
use Jmluang\SsoConsumer\Support\TicketVerifier;
use Jmluang\SsoConsumer\Tests\Fixtures\TicketFactory;
use Jmluang\SsoConsumer\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class TicketVerifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sso-consumer.public_key', (string) file_get_contents(__DIR__.'/../Fixtures/keys/test-public.pem'));
        config()->set('sso-consumer.leeway_seconds', 5);
    }

    public function test_valid_ticket_returns_claims(): void
    {
        [$ticket, $claims] = TicketFactory::valid();

        $verified = app(TicketVerifier::class)->verify($ticket, 'shanghai.florentiavillage.com');

        $this->assertSame($claims['iss'], $verified['iss']);
        $this->assertSame($claims['aud'], $verified['aud']);
        $this->assertSame($claims['phone'], $verified['phone']);
        $this->assertSame($claims['email'], $verified['email']);
        $this->assertSame($claims['name'], $verified['name']);
        $this->assertSame($claims['tenant_domain'], $verified['tenant_domain']);
        $this->assertSame($claims['jti'], $verified['jti']);
        $this->assertSame($claims['v'], $verified['v']);
    }

    public function test_v2_ticket_missing_phone_throws_invalid_ticket_exception(): void
    {
        [$ticket] = TicketFactory::valid(['phone' => null]);

        $this->expectException(InvalidTicketException::class);

        app(TicketVerifier::class)->verify($ticket, 'shanghai.florentiavillage.com');
    }

    #[DataProvider('requiredClaimProvider')]
    public function test_ticket_missing_required_claim_throws_invalid_ticket_exception(string $claim): void
    {
        [$ticket] = TicketFactory::valid([$claim => null]);

        $this->expectException(InvalidTicketException::class);

        app(TicketVerifier::class)->verify($ticket, 'shanghai.florentiavillage.com');
    }

    public function test_v2_ticket_with_sub_different_from_phone_throws_invalid_ticket_exception(): void
    {
        [$ticket] = TicketFactory::valid(['sub' => 'different-subject']);

        $this->expectException(InvalidTicketException::class);

        app(TicketVerifier::class)->verify($ticket, 'shanghai.florentiavillage.com');
    }

    public function test_ticket_with_invalid_jti_shape_throws_invalid_ticket_exception(): void
    {
        [$ticket] = TicketFactory::valid(['jti' => 'not-hex']);

        $this->expectException(InvalidTicketException::class);

        app(TicketVerifier::class)->verify($ticket, 'shanghai.florentiavillage.com');
    }

    public function test_ticket_with_tenant_system_different_from_audience_throws_invalid_ticket_exception(): void
    {
        [$ticket] = TicketFactory::valid(['tenant_system' => 'gd']);

        $this->expectException(InvalidTicketException::class);

        app(TicketVerifier::class)->verify($ticket, 'shanghai.florentiavillage.com');
    }

    public function test_v1_email_only_ticket_is_still_accepted_when_supported(): void
    {
        $verified = app(TicketVerifier::class)->verify(
            TicketFactory::v1EmailOnly(),
            'shanghai.florentiavillage.com',
        );

        $this->assertSame(1, $verified['v']);
        $this->assertSame('alice@florentiavillage.com', $verified['sub']);
        $this->assertSame('alice@florentiavillage.com', $verified['email']);
        $this->assertArrayNotHasKey('phone', $verified);
    }

    public function test_malformed_ticket_throws_invalid_ticket_exception(): void
    {
        $this->expectException(InvalidTicketException::class);

        app(TicketVerifier::class)->verify(TicketFactory::unsignedMalformed(), 'shanghai.florentiavillage.com');
    }

    public function test_wrong_algorithm_throws_invalid_ticket_exception(): void
    {
        $this->expectException(InvalidTicketException::class);

        app(TicketVerifier::class)->verify(TicketFactory::wrongAlg(), 'shanghai.florentiavillage.com');
    }

    public function test_bad_signature_throws_invalid_ticket_exception(): void
    {
        $this->expectException(InvalidTicketException::class);

        app(TicketVerifier::class)->verify(TicketFactory::badSignature(), 'shanghai.florentiavillage.com');
    }

    public function test_wrong_version_throws_unsupported_version_exception(): void
    {
        $this->expectException(UnsupportedVersionException::class);

        app(TicketVerifier::class)->verify(TicketFactory::wrongVersion(99), 'shanghai.florentiavillage.com');
    }

    public function test_wrong_issuer_throws_invalid_ticket_exception(): void
    {
        $this->expectException(InvalidTicketException::class);

        app(TicketVerifier::class)->verify(TicketFactory::wrongIssuer(), 'shanghai.florentiavillage.com');
    }

    public function test_expired_ticket_throws_expired_ticket_exception(): void
    {
        $this->expectException(ExpiredTicketException::class);

        app(TicketVerifier::class)->verify(TicketFactory::expired(), 'shanghai.florentiavillage.com');
    }

    public function test_future_iat_beyond_leeway_throws_invalid_ticket_exception(): void
    {
        [$ticket] = TicketFactory::valid([
            'iat' => time() + 30,
            'exp' => time() + 120,
        ]);

        $this->expectException(InvalidTicketException::class);

        app(TicketVerifier::class)->verify($ticket, 'shanghai.florentiavillage.com');
    }

    public function test_future_nbf_beyond_leeway_throws_invalid_ticket_exception(): void
    {
        [$ticket] = TicketFactory::valid([
            'nbf' => time() + 30,
            'exp' => time() + 120,
        ]);

        $this->expectException(InvalidTicketException::class);

        app(TicketVerifier::class)->verify($ticket, 'shanghai.florentiavillage.com');
    }

    public function test_wrong_audience_throws_audience_mismatch_exception(): void
    {
        $this->expectException(AudienceMismatchException::class);

        app(TicketVerifier::class)->verify(TicketFactory::wrongAudience('gd'), 'shanghai.florentiavillage.com');
    }

    public function test_wrong_tenant_domain_throws_tenant_mismatch_exception(): void
    {
        $this->expectException(TenantMismatchException::class);

        app(TicketVerifier::class)->verify(
            TicketFactory::wrongTenantDomain('beijing.florentiavillage.com'),
            'shanghai.florentiavillage.com'
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function requiredClaimProvider(): array
    {
        return [
            'iss' => ['iss'],
            'aud' => ['aud'],
            'sub' => ['sub'],
            'tenant_domain' => ['tenant_domain'],
            'tenant_id' => ['tenant_id'],
            'tenant_system' => ['tenant_system'],
            'jti' => ['jti'],
            'v' => ['v'],
            'iat' => ['iat'],
            'exp' => ['exp'],
        ];
    }
}
