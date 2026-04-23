<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Events\Dispatchable;

class SsoLoginSucceeded
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>  $claims
     */
    public function __construct(
        public readonly Authenticatable $user,
        public readonly array $claims,
        public readonly string $requestId,
    ) {}
}
