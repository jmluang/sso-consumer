<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Exceptions;

class AudienceMismatchException extends SsoConsumerException
{
    public const ERROR_CODE = 'audience_mismatch';
}
