<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Tests\Feature;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Jmluang\SsoConsumer\Contracts\SsoUserResolver;
use Jmluang\SsoConsumer\Events\SsoLoginFailed;
use Jmluang\SsoConsumer\Events\SsoLoginSucceeded;
use Jmluang\SsoConsumer\Exceptions\AudienceMismatchException;
use Jmluang\SsoConsumer\Exceptions\ExpiredTicketException;
use Jmluang\SsoConsumer\Exceptions\IdentityConflictException;
use Jmluang\SsoConsumer\Exceptions\InvalidTicketException;
use Jmluang\SsoConsumer\Exceptions\ReplayedTicketException;
use Jmluang\SsoConsumer\Exceptions\ResolverFailedException;
use Jmluang\SsoConsumer\Exceptions\SsoConsumerException;
use Jmluang\SsoConsumer\Exceptions\TenantMismatchException;
use Jmluang\SsoConsumer\Exceptions\UnsupportedVersionException;
use Jmluang\SsoConsumer\Exceptions\UserNotFoundException;
use Jmluang\SsoConsumer\Support\JtiReplayGuard;
use Jmluang\SsoConsumer\Support\TicketVerifier;
use Jmluang\SsoConsumer\Tests\Fixtures\TicketFactory;
use Jmluang\SsoConsumer\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class ConsumeControllerTest extends TestCase
{
    public function test_successful_consume_redirects_dispatches_event_and_claims_jti(): void
    {
        Event::fake();
        [$ticket, $claims] = TicketFactory::valid();
        $this->bindResolverReturning(new GenericUser([
            'id' => 123,
            'phone' => $claims['phone'],
            'email' => $claims['email'],
        ]));

        $response = $this
            ->get('http://shanghai.florentiavillage.com/admin-app/sso/consume?ticket='.$ticket);

        $response->assertRedirect('/admin-app/dashboard');
        $this->assertTrue(Cache::has('sso_consumer:jti:'.$claims['jti']));
        Event::assertDispatched(
            SsoLoginSucceeded::class,
            fn (SsoLoginSucceeded $event): bool => $event->claims['jti'] === $claims['jti']
                && $event->user->getAuthIdentifier() === 123
        );
    }

    public function test_consume_verifies_against_http_host_with_non_standard_port(): void
    {
        Event::fake();
        $claims = TicketFactory::valid([
            'tenant_domain' => '127.0.0.1:8000',
        ])[1];
        $this->bindVerifierExpectingHost('127.0.0.1:8000', $claims);
        $this->bindResolverReturning(new GenericUser(['id' => 123, 'email' => $claims['email']]));

        $response = $this->get('http://127.0.0.1:8000/admin-app/sso/consume?ticket=header.payload.signature');

        $response->assertRedirect('/admin-app/dashboard');
    }

    public function test_configured_expected_host_is_used_instead_of_request_host(): void
    {
        Event::fake();
        config()->set('sso-consumer.expected_host', 'shanghai.florentiavillage.com');
        $claims = TicketFactory::valid([
            'tenant_domain' => 'shanghai.florentiavillage.com',
        ])[1];
        $this->bindVerifierExpectingHost('shanghai.florentiavillage.com', $claims);
        $this->bindResolverReturning(new GenericUser(['id' => 123, 'email' => $claims['email']]));

        $response = $this->get('http://spoofed.example.test/admin-app/sso/consume?ticket=header.payload.signature');

        $response->assertRedirect('/admin-app/dashboard');
    }

    public function test_replay_ttl_includes_leeway_and_minimum_floor(): void
    {
        Event::fake();
        config()->set('sso-consumer.leeway_seconds', 5);
        config()->set('sso-consumer.replay_min_ttl_seconds', 60);
        $claims = TicketFactory::valid([
            'exp' => time() - 1,
        ])[1];
        $this->bindVerifierReturning($claims);
        $this->app->instance(JtiReplayGuard::class, new class extends JtiReplayGuard
        {
            public int $ttlSeconds = 0;

            public function claim(string $jti, int $ttlSeconds): void
            {
                $this->ttlSeconds = $ttlSeconds;
            }
        });
        $this->bindResolverReturning(new GenericUser(['id' => 123, 'email' => $claims['email']]));

        $response = $this
            ->withServerVariables(['HTTP_HOST' => 'shanghai.florentiavillage.com'])
            ->get('/admin-app/sso/consume?ticket=header.payload.signature');

        $response->assertRedirect('/admin-app/dashboard');
        $guard = $this->app->make(JtiReplayGuard::class);
        $this->assertSame(60, $guard->ttlSeconds);
    }

    public function test_missing_ticket_redirects_to_failure_redirect_with_flash_and_failed_event(): void
    {
        Event::fake();

        $response = $this->get('/admin-app/sso/consume');

        $response->assertRedirect('/admin-app/login');
        $response->assertSessionHas('admin_sso_error');
        Event::assertDispatched(
            SsoLoginFailed::class,
            fn (SsoLoginFailed $event): bool => $event->errorCode === 'ticket_missing'
                && $event->claims === null
                && $event->rawTicketHead === null
        );
    }

    /**
     * @param  class-string<SsoConsumerException>  $exceptionClass
     */
    #[DataProvider('verifierExceptionProvider')]
    public function test_verifier_exceptions_render_error_page_and_dispatch_failed_event(
        string $exceptionClass,
        string $errorCode
    ): void {
        Event::fake();
        $this->bindVerifierThrowing(new $exceptionClass);
        $this->bindResolverReturning(new GenericUser(['id' => 123]));

        $response = $this
            ->withServerVariables(['HTTP_HOST' => 'shanghai.florentiavillage.com'])
            ->get('/admin-app/sso/consume?ticket=header.payload.signature');

        $response->assertStatus(400);
        $response->assertSee($errorCode);
        Event::assertDispatched(
            SsoLoginFailed::class,
            fn (SsoLoginFailed $event): bool => $event->errorCode === $errorCode
                && $event->rawTicketHead === 'header.p...'
                && $event->exception instanceof $exceptionClass
        );
    }

    public function test_replayed_ticket_renders_error_page_and_dispatches_failed_event(): void
    {
        Event::fake();
        [, $claims] = TicketFactory::valid();
        $this->bindVerifierReturning($claims);
        $this->app->instance(JtiReplayGuard::class, new class extends JtiReplayGuard
        {
            public function claim(string $jti, int $ttlSeconds): void
            {
                throw new ReplayedTicketException;
            }
        });
        $this->bindResolverReturning(new GenericUser(['id' => 123]));

        $response = $this
            ->withServerVariables(['HTTP_HOST' => 'shanghai.florentiavillage.com'])
            ->get('/admin-app/sso/consume?ticket=header.payload.signature');

        $response->assertStatus(400);
        $response->assertSee('ticket_replayed');
        Event::assertDispatched(
            SsoLoginFailed::class,
            fn (SsoLoginFailed $event): bool => $event->errorCode === 'ticket_replayed'
                && $event->claims === $claims
        );
    }

    public function test_conflicting_phone_and_email_lookups_are_rejected_at_library_level(): void
    {
        Event::fake();
        [, $claims] = TicketFactory::valid();
        $this->bindVerifierReturning($claims);

        $userByPhone = new GenericUser(['id' => 11, 'phone' => $claims['phone']]);
        $userByEmail = new GenericUser(['id' => 22, 'email' => $claims['email']]);

        $resolver = new class($userByPhone, $userByEmail) implements SsoUserResolver
        {
            public bool $loginCalled = false;

            public function __construct(
                private readonly Authenticatable $userByPhone,
                private readonly Authenticatable $userByEmail,
            ) {}

            public function findByPhone(string $phone, array $claims, Request $request): ?Authenticatable
            {
                return $this->userByPhone;
            }

            public function findByEmail(string $email, array $claims, Request $request): ?Authenticatable
            {
                return $this->userByEmail;
            }

            public function login(Authenticatable $user, array $claims, Request $request): void
            {
                $this->loginCalled = true;
            }
        };
        $this->app->instance(SsoUserResolver::class, $resolver);

        $response = $this
            ->withServerVariables(['HTTP_HOST' => 'shanghai.florentiavillage.com'])
            ->get('/admin-app/sso/consume?ticket=header.payload.signature');

        $response->assertStatus(400);
        $response->assertSee('identity_conflict');
        $this->assertFalse($resolver->loginCalled, 'login() must not be invoked when phone and email collide');
        Event::assertDispatched(
            SsoLoginFailed::class,
            fn (SsoLoginFailed $event): bool => $event->errorCode === IdentityConflictException::ERROR_CODE
                && $event->claims === $claims
                && $event->exception instanceof IdentityConflictException
        );
        Event::assertNotDispatched(SsoLoginSucceeded::class);
    }

    public function test_matching_phone_and_email_lookups_log_in_once(): void
    {
        Event::fake();
        [, $claims] = TicketFactory::valid();
        $this->bindVerifierReturning($claims);

        $shared = new GenericUser([
            'id' => 77,
            'phone' => $claims['phone'],
            'email' => $claims['email'],
        ]);

        $resolver = new class($shared) implements SsoUserResolver
        {
            public int $phoneCalls = 0;

            public int $emailCalls = 0;

            public int $loginCalls = 0;

            public function __construct(private readonly Authenticatable $user) {}

            public function findByPhone(string $phone, array $claims, Request $request): ?Authenticatable
            {
                $this->phoneCalls++;

                return $this->user;
            }

            public function findByEmail(string $email, array $claims, Request $request): ?Authenticatable
            {
                $this->emailCalls++;

                return $this->user;
            }

            public function login(Authenticatable $user, array $claims, Request $request): void
            {
                $this->loginCalls++;
            }
        };
        $this->app->instance(SsoUserResolver::class, $resolver);

        $response = $this
            ->withServerVariables(['HTTP_HOST' => 'shanghai.florentiavillage.com'])
            ->get('/admin-app/sso/consume?ticket=header.payload.signature');

        $response->assertRedirect('/admin-app/dashboard');
        $this->assertSame(1, $resolver->phoneCalls);
        $this->assertSame(1, $resolver->emailCalls);
        $this->assertSame(1, $resolver->loginCalls);
    }

    public function test_v1_ticket_skips_phone_lookup(): void
    {
        Event::fake();
        [, $claims] = TicketFactory::valid([
            'v' => 1,
            'phone' => null,
            'sub' => 'legacy@florentiavillage.com',
            'email' => 'legacy@florentiavillage.com',
        ]);
        unset($claims['phone']);
        $this->bindVerifierReturning($claims);

        $resolver = new class implements SsoUserResolver
        {
            public int $phoneCalls = 0;

            public int $emailCalls = 0;

            public int $loginCalls = 0;

            public function findByPhone(string $phone, array $claims, Request $request): ?Authenticatable
            {
                $this->phoneCalls++;

                return null;
            }

            public function findByEmail(string $email, array $claims, Request $request): ?Authenticatable
            {
                $this->emailCalls++;

                return new GenericUser(['id' => 1, 'email' => $email]);
            }

            public function login(Authenticatable $user, array $claims, Request $request): void
            {
                $this->loginCalls++;
            }
        };
        $this->app->instance(SsoUserResolver::class, $resolver);

        $response = $this
            ->withServerVariables(['HTTP_HOST' => 'shanghai.florentiavillage.com'])
            ->get('/admin-app/sso/consume?ticket=header.payload.signature');

        $response->assertRedirect('/admin-app/dashboard');
        $this->assertSame(0, $resolver->phoneCalls, 'v1 tickets carry no phone claim');
        $this->assertSame(1, $resolver->emailCalls);
        $this->assertSame(1, $resolver->loginCalls);
    }

    public function test_login_hook_failure_wraps_into_resolver_failed(): void
    {
        Event::fake();
        [, $claims] = TicketFactory::valid();
        $this->bindVerifierReturning($claims);

        $original = new RuntimeException('login exploded');
        $handler = Mockery::spy(ExceptionHandler::class);
        $this->app->instance(ExceptionHandler::class, $handler);

        $this->app->instance(SsoUserResolver::class, new class($original) implements SsoUserResolver
        {
            public function __construct(private readonly RuntimeException $exception) {}

            public function findByPhone(string $phone, array $claims, Request $request): ?Authenticatable
            {
                return new GenericUser(['id' => 1, 'phone' => $phone]);
            }

            public function findByEmail(string $email, array $claims, Request $request): ?Authenticatable
            {
                return null;
            }

            public function login(Authenticatable $user, array $claims, Request $request): void
            {
                throw $this->exception;
            }
        });

        $response = $this
            ->withServerVariables(['HTTP_HOST' => 'shanghai.florentiavillage.com'])
            ->get('/admin-app/sso/consume?ticket=header.payload.signature');

        $response->assertStatus(400);
        $response->assertSee('resolver_failed');
        $handler->shouldHaveReceived('report')
            ->once()
            ->with(Mockery::on(fn ($e): bool => $e instanceof ResolverFailedException && $e->getPrevious() === $original));
    }

    public function test_resolver_returning_null_renders_user_not_found(): void
    {
        Event::fake();
        [, $claims] = TicketFactory::valid();
        $this->bindVerifierReturning($claims);
        $this->bindResolverReturning(null);

        $response = $this
            ->withServerVariables(['HTTP_HOST' => 'shanghai.florentiavillage.com'])
            ->get('/admin-app/sso/consume?ticket=header.payload.signature');

        $response->assertStatus(400);
        $response->assertSee('user_not_found');
        Event::assertDispatched(
            SsoLoginFailed::class,
            fn (SsoLoginFailed $event): bool => $event->errorCode === UserNotFoundException::ERROR_CODE
                && $event->claims === $claims
        );
    }

    public function test_resolver_exception_is_wrapped_reported_and_rendered_as_resolver_failed(): void
    {
        Event::fake();
        [, $claims] = TicketFactory::valid();
        $this->bindVerifierReturning($claims);

        $original = new RuntimeException('resolver exploded');
        $handler = Mockery::spy(ExceptionHandler::class);
        $this->app->instance(ExceptionHandler::class, $handler);

        $this->app->instance(SsoUserResolver::class, new class($original) implements SsoUserResolver
        {
            public function __construct(private readonly RuntimeException $exception) {}

            public function findByPhone(string $phone, array $claims, Request $request): ?Authenticatable
            {
                throw $this->exception;
            }

            public function findByEmail(string $email, array $claims, Request $request): ?Authenticatable
            {
                return null;
            }

            public function login(Authenticatable $user, array $claims, Request $request): void
            {
                // unreachable: lookup throws first
            }
        });

        $response = $this
            ->withServerVariables(['HTTP_HOST' => 'shanghai.florentiavillage.com'])
            ->get('/admin-app/sso/consume?ticket=header.payload.signature');

        $response->assertStatus(400);
        $response->assertSee('resolver_failed');
        $handler->shouldHaveReceived('report')
            ->once()
            ->with(Mockery::on(fn ($e): bool => $e instanceof ResolverFailedException && $e->getPrevious() === $original));
        Event::assertDispatched(
            SsoLoginFailed::class,
            fn (SsoLoginFailed $event): bool => $event->errorCode === ResolverFailedException::ERROR_CODE
                && $event->claims === $claims
                && $event->exception instanceof ResolverFailedException
                && $event->exception->getPrevious() === $original
        );
    }

    /**
     * @return array<string, array{0: class-string<SsoConsumerException>, 1: string}>
     */
    public static function verifierExceptionProvider(): array
    {
        return [
            'invalid ticket' => [InvalidTicketException::class, InvalidTicketException::ERROR_CODE],
            'expired ticket' => [ExpiredTicketException::class, ExpiredTicketException::ERROR_CODE],
            'unsupported version' => [UnsupportedVersionException::class, UnsupportedVersionException::ERROR_CODE],
            'audience mismatch' => [AudienceMismatchException::class, AudienceMismatchException::ERROR_CODE],
            'tenant mismatch' => [TenantMismatchException::class, TenantMismatchException::ERROR_CODE],
        ];
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function bindVerifierReturning(array $claims): void
    {
        $this->app->instance(TicketVerifier::class, new class($claims) extends TicketVerifier
        {
            /**
             * @param  array<string, mixed>  $claims
             */
            public function __construct(private readonly array $claims) {}

            public function verify(string $ticket, string $requestHost): array
            {
                return $this->claims;
            }
        });
    }

    private function bindVerifierThrowing(SsoConsumerException $exception): void
    {
        $this->app->instance(TicketVerifier::class, new class($exception) extends TicketVerifier
        {
            public function __construct(private readonly SsoConsumerException $exception) {}

            public function verify(string $ticket, string $requestHost): array
            {
                throw $this->exception;
            }
        });
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function bindVerifierExpectingHost(string $expectedHost, array $claims): void
    {
        $this->app->instance(TicketVerifier::class, new class($expectedHost, $claims) extends TicketVerifier
        {
            /**
             * @param  array<string, mixed>  $claims
             */
            public function __construct(
                private readonly string $expectedHost,
                private readonly array $claims,
            ) {}

            public function verify(string $ticket, string $requestHost): array
            {
                Assert::assertSame($this->expectedHost, $requestHost);

                return $this->claims;
            }
        });
    }

    private function bindResolverReturning(?Authenticatable $user): void
    {
        $this->app->instance(SsoUserResolver::class, new class($user) implements SsoUserResolver
        {
            public function __construct(private readonly ?Authenticatable $user) {}

            public function findByPhone(string $phone, array $claims, Request $request): ?Authenticatable
            {
                return $this->user;
            }

            public function findByEmail(string $email, array $claims, Request $request): ?Authenticatable
            {
                return $this->user;
            }

            public function login(Authenticatable $user, array $claims, Request $request): void
            {
                // no-op
            }
        });
    }
}
