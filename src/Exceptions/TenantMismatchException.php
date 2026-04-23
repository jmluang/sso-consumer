<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Exceptions;

class TenantMismatchException extends SsoConsumerException
{
    public const ERROR_CODE = 'tenant_mismatch';
}
