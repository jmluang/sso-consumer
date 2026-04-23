<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Exceptions;

class UnsupportedVersionException extends SsoConsumerException
{
    public const ERROR_CODE = 'ticket_version_unsupported';
}
