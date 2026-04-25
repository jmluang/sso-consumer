<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Jmluang\SsoConsumer\Contracts\SsoUserResolver;
use Jmluang\SsoConsumer\Events\SsoLoginFailed;
use Jmluang\SsoConsumer\Events\SsoLoginSucceeded;
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
     *   4. app(SsoUserResolver::class)->resolve($claims, $request)
     *      - null → UserNotFoundException
     *      - throws → ResolverFailedException (wrapped)
     *   5. On success: dispatch SsoLoginSucceeded, 302 success_redirect.
     *   6. On any exception: dispatch SsoLoginFailed, render error view.
     *
     * TODO(OpenCode): implement per spec.
     */
    public function __invoke(Request $request): Response
    {
        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();
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

            try {
                $user = app(SsoUserResolver::class)->resolve($claims, $request);
            } catch (Throwable $e) {
                throw new ResolverFailedException(previous: $e);
            }

            if ($user === null) {
                throw new UserNotFoundException;
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

        return $request->getHttpHost();
    }

    private function replayTtlSeconds(int $expiresAt): int
    {
        $ttlWithLeeway = $expiresAt - time() + (int) config('sso-consumer.leeway_seconds', 5);

        return max((int) config('sso-consumer.replay_min_ttl_seconds', 60), $ttlWithLeeway);
    }

    private function errorResponse(string $errorCode, string $requestId): Response
    {
        return response()->view((string) config('sso-consumer.error_view', 'sso-consumer::error'), [
            'errorCode' => $errorCode,
            'errorMessage' => trans("sso-consumer::sso.{$errorCode}"),
            'portalUrl' => app(PortalUrlBuilder::class)->portalUrl(),
            'loginUrl' => config('sso-consumer.failure_redirect'),
            'requestId' => $requestId,
        ], 400);
    }

    private function ticketHead(string $ticket): string
    {
        return substr($ticket, 0, 8).'...';
    }
}
