<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Exceptions;

class ResolverFailedException extends SsoConsumerException
{
    public const ERROR_CODE = 'resolver_failed';
}
