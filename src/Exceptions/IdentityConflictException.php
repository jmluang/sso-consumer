<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Exceptions;

class IdentityConflictException extends SsoConsumerException
{
    public const ERROR_CODE = 'identity_conflict';

    /**
     * Identifiers (typically primary keys) of the two local users that the
     * verified phone and email claims resolved to. Carried so audit listeners
     * can correlate the conflict without re-querying the database. PII (the
     * raw phone / email) intentionally lives only in the `claims` array on
     * the `SsoLoginFailed` event.
     */
    public function __construct(
        public readonly mixed $phoneIdentifier = null,
        public readonly mixed $emailIdentifier = null,
    ) {
        parent::__construct();
    }
}
