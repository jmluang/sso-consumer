<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Exceptions;

class InvalidTicketException extends SsoConsumerException
{
    public const ERROR_CODE = 'ticket_invalid';
}
