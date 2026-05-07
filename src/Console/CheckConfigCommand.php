<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Console;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\FileStore;
use Illuminate\Cache\NullStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Jmluang\SsoConsumer\Contracts\SsoUserResolver;
use Throwable;

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
        $checks = [
            $this->checkSystemCode(),
            $this->checkPortalUrl(),
            $this->checkPublicKey(),
            $this->checkExpectedHost(),
            $this->checkResolver(),
            $this->checkCacheStore(),
            $this->checkConsumeMiddleware(),
        ];

        foreach ($checks as [$passed, $label, $message]) {
            $this->line(sprintf('%s %s - %s', $passed ? '✓' : '✗', $label, $message));
        }

        return collect($checks)->every(fn (array $check): bool => $check[0] === true)
            ? self::SUCCESS
            : self::FAILURE;
    }

    /**
     * @return array{0: bool, 1: string, 2: string}
     */
    private function checkSystemCode(): array
    {
        $value = config('sso-consumer.system_code');

        return [
            is_string($value) && $value !== '',
            'system code',
            is_string($value) && $value !== '' ? 'configured' : 'missing',
        ];
    }

    /**
     * @return array{0: bool, 1: string, 2: string}
     */
    private function checkPortalUrl(): array
    {
        $url = config('sso-consumer.portal_url');

        if (! is_string($url) || $url === '') {
            return [false, 'portal url', 'missing'];
        }

        try {
            $response = Http::timeout(5)->head($url);
        } catch (Throwable $e) {
            return [false, 'portal url', 'HEAD request failed: '.$e->getMessage()];
        }

        $status = $response->status();

        return [
            $status >= 200 && $status < 400,
            'portal url',
            'HEAD returned HTTP '.$status,
        ];
    }

    /**
     * @return array{0: bool, 1: string, 2: string}
     */
    private function checkPublicKey(): array
    {
        $publicKey = config('sso-consumer.public_key');

        if (! is_string($publicKey) || $publicKey === '') {
            return [false, 'public key', 'missing'];
        }

        $key = @openssl_pkey_get_public($publicKey);

        if ($key === false) {
            return [false, 'public key', 'cannot parse PEM public key'];
        }

        $details = openssl_pkey_get_details($key);
        $isRsa = is_array($details) && ($details['type'] ?? null) === OPENSSL_KEYTYPE_RSA;

        return [
            $isRsa,
            'public key',
            $isRsa ? 'valid RSA public key' : 'not an RSA public key',
        ];
    }

    /**
     * @return array{0: bool, 1: string, 2: string}
     */
    private function checkExpectedHost(): array
    {
        $hosts = $this->configuredExpectedHosts();
        $isConfigured = $hosts !== [];

        if (app()->isProduction()) {
            return [
                $isConfigured,
                'expected host',
                $isConfigured ? 'configured' : 'missing in production',
            ];
        }

        return [
            true,
            'expected host',
            $isConfigured ? 'configured' : 'optional outside production',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function configuredExpectedHosts(): array
    {
        return array_values(array_unique(array_merge(
            $this->normalizeHostList(config('sso-consumer.expected_host')),
            $this->normalizeHostList(config('sso-consumer.expected_hosts')),
        )));
    }

    /**
     * @return array<int, string>
     */
    private function normalizeHostList(mixed $value): array
    {
        if (is_string($value)) {
            $value = explode(',', $value);
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(
            array_map(
                static fn (mixed $host): string => is_string($host) ? trim($host) : '',
                $value
            ),
            static fn (string $host): bool => $host !== ''
        ));
    }

    /**
     * @return array{0: bool, 1: string, 2: string}
     */
    private function checkResolver(): array
    {
        $resolver = config('sso-consumer.resolver');

        if (! is_string($resolver) || $resolver === '') {
            return [false, 'resolver', 'missing'];
        }

        if (! class_exists($resolver)) {
            return [false, 'resolver', 'class does not exist'];
        }

        $implementsContract = is_subclass_of($resolver, SsoUserResolver::class);

        return [
            $implementsContract,
            'resolver',
            $implementsContract ? 'implements SsoUserResolver' : 'does not implement SsoUserResolver',
        ];
    }

    /**
     * @return array{0: bool, 1: string, 2: string}
     */
    private function checkCacheStore(): array
    {
        try {
            $store = Cache::store(config('sso-consumer.replay_cache_store'));
            $store->put('sso_check', 1, 5);
        } catch (Throwable $e) {
            return [false, 'cache store', 'write failed: '.$e->getMessage()];
        }

        $driver = $store->getStore();
        $driverClass = get_class($driver);

        // Blocklist the drivers we know cannot guarantee atomic add() across
        // workers. Anything else (redis, memcached, database, dynamodb,
        // custom redis-backed stores, etc.) passes — false negatives here
        // are safer than false positives that lock out legitimate setups.
        $nonAtomicDrivers = [
            ArrayStore::class,
            FileStore::class,
            NullStore::class,
        ];

        foreach ($nonAtomicDrivers as $nonAtomic) {
            if ($driver instanceof $nonAtomic) {
                if (app()->isProduction()) {
                    return [false, 'cache store', "{$driverClass} is not safe for replay protection in production (use redis/memcached/database/dynamodb)"];
                }

                return [true, 'cache store', "writable ({$driverClass}, non-atomic — OK for dev only)"];
            }
        }

        return [true, 'cache store', "writable ({$driverClass})"];
    }

    /**
     * @return array{0: bool, 1: string, 2: string}
     */
    private function checkConsumeMiddleware(): array
    {
        $middleware = config('sso-consumer.consume_middleware', []);

        if (! is_array($middleware) || $middleware === []) {
            return [true, 'consume middleware', 'none configured'];
        }

        $router = app('router');
        $aliases = $router->getMiddleware();
        $groups = $router->getMiddlewareGroups();
        $missing = [];

        foreach ($middleware as $entry) {
            if (! is_string($entry)) {
                $missing[] = 'non-string middleware';

                continue;
            }

            // Strip parameters (e.g. `throttle:sso-consume` → `throttle`) so
            // we resolve the registered alias rather than the parameterized
            // entry string, which is never registered as a key.
            $alias = explode(':', $entry, 2)[0];

            if (isset($aliases[$alias]) || isset($groups[$alias]) || class_exists($alias)) {
                continue;
            }

            $missing[] = $entry;
        }

        return [
            $missing === [],
            'consume middleware',
            $missing === [] ? 'registered' : 'missing: '.implode(', ', $missing),
        ];
    }
}
