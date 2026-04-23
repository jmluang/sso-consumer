<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Exceptions;

class ExpiredTicketException extends SsoConsumerException
{
    public const ERROR_CODE = 'ticket_expired';
}
