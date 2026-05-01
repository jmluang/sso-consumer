<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Exceptions;

class IdentityConflictException extends SsoConsumerException
{
    public const ERROR_CODE = 'identity_conflict';
}
