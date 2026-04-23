<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Exceptions;

use RuntimeException;

abstract class SsoConsumerException extends RuntimeException
{
    /**
     * Stable error code surfaced to users and audit logs.
     * See docs/sso/contracts/error-codes.md.
     */
    public const ERROR_CODE = 'sso_error';

    public function errorCode(): string
    {
        return static::ERROR_CODE;
    }
}
