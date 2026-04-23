<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Throwable;

class SsoLoginFailed
{
    use Dispatchable;

    /**
     * @param  array<string, mixed>|null  $claims  Partially parsed claims, if available.
     * @param  string|null  $rawTicketHead  First 8 chars of the ticket + "..." (for log correlation).
     */
    public function __construct(
        public readonly string $errorCode,
        public readonly ?array $claims,
        public readonly ?string $rawTicketHead,
        public readonly string $requestId,
        public readonly ?Throwable $exception = null,
    ) {}
}
