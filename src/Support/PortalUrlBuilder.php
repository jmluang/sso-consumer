<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Support;

class PortalUrlBuilder
{
    /**
     * Return the portal root URL from config.
     * Used by the LoginButton component and the error page "return to portal" link.
     *
     * TODO(OpenCode): implement — likely just a config getter, but if the portal
     * URL needs query parameters (e.g. ?return_to=...) later, centralize it here.
     */
    public function portalUrl(): string
    {
        $url = config('sso-consumer.portal_url');

        if (! is_string($url) || $url === '') {
            throw new \RuntimeException('sso-consumer.portal_url is not configured.');
        }

        return rtrim($url, '/');
    }
}
