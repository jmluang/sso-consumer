<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Console;

use Illuminate\Console\Command;

class CheckConfigCommand extends Command
{
    protected $signature = 'sso:check';

    protected $description = 'Verify the sso-consumer package configuration is ready for production.';

    /**
     * Checks to perform (TODO: implement):
     *   - portal_url set and reachable (HTTP 2xx/3xx)
     *   - public_key parseable as RSA PEM
     *   - system_code set
     *   - resolver class exists and implements SsoUserResolver
     *   - cache store writable
     * Print each result with ✓/✗; exit 0 if all pass, 1 otherwise.
     */
    public function handle(): int
    {
        $this->warn('sso:check not implemented yet.');

        return self::SUCCESS;
    }
}
