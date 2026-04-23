<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Exceptions;

class UserNotFoundException extends SsoConsumerException
{
    public const ERROR_CODE = 'user_not_found';
}
