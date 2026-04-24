<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\View\Component;
use Jmluang\SsoConsumer\Support\PortalUrlBuilder;

class LoginButton extends Component
{
    public string $portalUrl;

    public function __construct(
        public ?string $label = null,
        public ?string $class = null,
    ) {
        $this->portalUrl = app(PortalUrlBuilder::class)->portalUrl();
        $this->label ??= 'Sign in with SSO';
    }

    public function render(): View
    {
        return ViewFactory::make('sso-consumer::components.login-button');
    }
}
