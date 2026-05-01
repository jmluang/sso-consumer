<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Jmluang\SsoConsumer\Contracts\SsoUserResolver;
use Jmluang\SsoConsumer\Events\SsoLoginFailed;
use Jmluang\SsoConsumer\Events\SsoLoginSucceeded;
use Jmluang\SsoConsumer\Exceptions\IdentityConflictException;
use Jmluang\SsoConsumer\Exceptions\ResolverFailedException;
use Jmluang\SsoConsumer\Exceptions\SsoConsumerException;
use Jmluang\SsoConsumer\Exceptions\UserNotFoundException;
use Jmluang\SsoConsumer\Support\JtiReplayGuard;
use Jmluang\SsoConsumer\Support\PortalUrlBuilder;
use Jmluang\SsoConsumer\Support\TicketVerifier;
use Throwable;

class ConsumeController extends Controller
{
    public function __construct(
        private readonly TicketVerifier $verifier,
        private readonly JtiReplayGuard $guard,
    ) {}

    /**
     * GET {consume_path}?ticket=...
     *
     * Flow (see docs/sso/contracts/consume-endpoint.md):
     *   1. Reject missing ticket → 302 failure_redirect with flash.
     *   2. TicketVerifier::verify($ticket, configured/request host)
     *      throws one of the SsoConsumer exceptions on any failure.
     *   3. JtiReplayGuard::claim($jti, $ttl) — throws ReplayedTicketException.
     *   4. Identity orchestration via SsoUserResolver:
     *      a. If $claims['phone'] is non-empty: findByPhone($phone, $claims, $request)
     *      b. If $claims['email'] is non-empty: findByEmail($email, $claims, $request)
     *      c. Both non-null and different identifiers → IdentityConflictException
     *      d. Both null → UserNotFoundException
     *      e. resolver->login($user, $claims, $request) — throws → ResolverFailedException
     *   5. On success: dispatch SsoLoginSucceeded, 302 success_redirect.
     *   6. On any exception: dispatch SsoLoginFailed, render error view.
     */
    public function __invoke(Request $request): Response
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();

        // Reject plaintext consumes in production: a ticket on the wire over
        // HTTP would leak through proxies, browser history and Referer headers.
        if (app()->isProduction() && ! $request->isSecure()) {
            SsoLoginFailed::dispatch('ticket_invalid', null, null, $requestId);

            return $this->errorResponse('ticket_invalid', $requestId);
        }

        $ticket = $request->query('ticket');

        if (! is_string($ticket) || $ticket === '') {
            $errorCode = 'ticket_missing';
            SsoLoginFailed::dispatch($errorCode, null, null, $requestId);
            $request->session()->flash('admin_sso_error', trans("sso-consumer::sso.{$errorCode}"));

            return $this->redirectResponse((string) config('sso-consumer.failure_redirect', '/admin-app/login'));
        }

        $claims = null;

        try {
            $claims = $this->verifier->verify($ticket, $this->expectedHost($request));
            $this->guard->claim((string) $claims['jti'], $this->replayTtlSeconds((int) $claims['exp']));

            $resolver = app(SsoUserResolver::class);
            $user = $this->resolveUser($resolver, $claims, $request);

            if ($user === null) {
                throw new UserNotFoundException;
            }

            try {
                $resolver->login($user, $claims, $request);
            } catch (Throwable $e) {
                throw new ResolverFailedException(previous: $e);
            }

            SsoLoginSucceeded::dispatch($user, $claims, $requestId);

            return $this->redirectResponse((string) config('sso-consumer.success_redirect', '/admin-app/dashboard'));
        } catch (ResolverFailedException $e) {
            report($e);

            SsoLoginFailed::dispatch($e->errorCode(), $claims, $this->ticketHead($ticket), $requestId, $e);

            return $this->errorResponse($e->errorCode(), $requestId);
        } catch (SsoConsumerException $e) {
            SsoLoginFailed::dispatch($e->errorCode(), $claims, $this->ticketHead($ticket), $requestId, $e);

            return $this->errorResponse($e->errorCode(), $requestId);
        } catch (Throwable $e) {
            $wrapped = new ResolverFailedException(previous: $e);
            report($wrapped);

            SsoLoginFailed::dispatch($wrapped->errorCode(), null, $this->ticketHead($ticket), $requestId, $wrapped);

            return $this->errorResponse($wrapped->errorCode(), $requestId);
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function resolveUser(SsoUserResolver $resolver, array $claims, Request $request): ?Authenticatable
    {
        $phone = $this->stringClaim($claims, 'phone');
        $email = $this->stringClaim($claims, 'email');

        try {
            $byPhone = $phone !== null ? $resolver->findByPhone($phone, $claims, $request) : null;
            $byEmail = $email !== null ? $resolver->findByEmail($email, $claims, $request) : null;
        } catch (Throwable $e) {
            throw new ResolverFailedException(previous: $e);
        }

        if ($byPhone !== null
            && $byEmail !== null
            && $byPhone->getAuthIdentifier() !== $byEmail->getAuthIdentifier()) {
            throw new IdentityConflictException(
                phoneIdentifier: $byPhone->getAuthIdentifier(),
                emailIdentifier: $byEmail->getAuthIdentifier(),
            );
        }

        return $byPhone ?? $byEmail;
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function stringClaim(array $claims, string $key): ?string
    {
        $value = $claims[$key] ?? null;

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function redirectResponse(string $location): Response
    {
        return new Response('', 302, ['Location' => $location]);
    }

    private function expectedHost(Request $request): string
    {
        $configuredHost = config('sso-consumer.expected_host');

        if (is_string($configuredHost) && trim($configuredHost) !== '') {
            return trim($configuredHost);
        }

        // In production we refuse to fall back to the request `Host` header:
        // a stolen ticket from a sibling tenant could otherwise pass the
        // `tenant_domain` check via a forged `Host:`. Failing here (rather
        // than at boot) keeps `sso:check` and other artisan commands usable
        // for diagnosing exactly this misconfiguration.
        if (app()->isProduction()) {
            throw new \RuntimeException(
                'sso-consumer: SSO_EXPECTED_HOST must be set in production. '
                .'Run `php artisan sso:check` and see README "Production Hardening".'
            );
        }

        return $request->getHttpHost();
    }

    private function replayTtlSeconds(int $expiresAt): int
    {
        $ttlWithLeeway = $expiresAt - time() + (int) config('sso-consumer.leeway_seconds', 5);

        return max((int) config('sso-consumer.replay_min_ttl_seconds', 60), $ttlWithLeeway);
    }

    private function errorResponse(string $errorCode, string $requestId): Response
    {
        // PortalUrlBuilder::portalUrl() throws when portal_url is not set.
        // We're already in an error path — degrade the link instead of
        // letting the error page itself blow up to a 500.
        try {
            $portalUrl = app(PortalUrlBuilder::class)->portalUrl();
        } catch (Throwable) {
            $portalUrl = (string) config('sso-consumer.failure_redirect', '#');
        }

        return response()->view((string) config('sso-consumer.error_view', 'sso-consumer::error'), [
            'errorCode' => $errorCode,
            'errorMessage' => trans("sso-consumer::sso.{$errorCode}"),
            'portalUrl' => $portalUrl,
            'loginUrl' => config('sso-consumer.failure_redirect'),
            'requestId' => $requestId,
        ], 400);
    }

    /**
     * Stable, low-cardinality fingerprint for log correlation.
     *
     * The first chars of a JWT are always the base64-encoded header
     * (`eyJhbGci...`), so prefix slicing carries zero entropy. Hash the full
     * ticket so two failed attempts on the same ticket share an id.
     */
    private function ticketHead(string $ticket): string
    {
        return substr(hash('sha256', $ticket), 0, 12);
    }
}
