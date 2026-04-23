<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class ConsumeController extends Controller
{
    /**
     * GET {consume_path}?ticket=...
     *
     * Flow (see docs/sso/contracts/consume-endpoint.md):
     *   1. Reject missing ticket → 302 failure_redirect with flash.
     *   2. TicketVerifier::verify($ticket, $request->getHost())
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
        abort(501, 'ConsumeController not implemented yet.');
    }
}
