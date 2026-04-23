<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Exceptions;

class ReplayedTicketException extends SsoConsumerException
{
    public const ERROR_CODE = 'ticket_replayed';
}
