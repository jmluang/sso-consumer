<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Tests\Feature;

use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use Jmluang\SsoConsumer\Tests\Fixtures\FakeSsoUserResolver;
use Jmluang\SsoConsumer\Tests\TestCase;

class CheckConfigCommandTest extends TestCase
{
    public function test_ready_configuration_exits_successfully(): void
    {
        Http::fake([
            'https://sso.test' => Http::response('', 302),
        ]);
        config()->set('sso-consumer.expected_host', 'tenant-a.test');
        config()->set('sso-consumer.resolver', FakeSsoUserResolver::class);
        config()->set('sso-consumer.consume_middleware', []);

        $this->artisan('sso:check')
            ->expectsOutputToContain('system code')
            ->expectsOutputToContain('public key')
            ->assertExitCode(0);
    }

    public function test_ready_configuration_accepts_multiple_expected_hosts(): void
    {
        Http::fake([
            'https://sso.test' => Http::response('', 302),
        ]);
        config()->set('sso-consumer.expected_host', null);
        config()->set('sso-consumer.expected_hosts', [
            'tenant-a.test',
            'tenant-b.test',
        ]);
        config()->set('sso-consumer.resolver', FakeSsoUserResolver::class);
        config()->set('sso-consumer.consume_middleware', []);

        $this->artisan('sso:check')
            ->expectsOutputToContain('expected host')
            ->expectsOutputToContain('configured')
            ->assertExitCode(0);
    }

    public function test_production_configuration_requires_expected_host(): void
    {
        Http::fake([
            'https://sso.test' => Http::response('', 302),
        ]);
        $this->app['env'] = 'production';
        config()->set('sso-consumer.expected_host', null);
        config()->set('sso-consumer.resolver', FakeSsoUserResolver::class);

        $this->artisan('sso:check')
            ->expectsOutputToContain('expected host')
            ->assertExitCode(1);
    }

    public function test_empty_public_key_exits_with_failure(): void
    {
        Http::fake([
            'https://sso.test' => Http::response('', 302),
        ]);
        config()->set('sso-consumer.public_key', '');
        config()->set('sso-consumer.resolver', FakeSsoUserResolver::class);

        $this->artisan('sso:check')
            ->expectsOutputToContain('public key')
            ->assertExitCode(1);
    }

    public function test_production_rejects_array_cache_store(): void
    {
        Http::fake([
            'https://sso.test' => Http::response('', 302),
        ]);
        $this->app['env'] = 'production';
        config()->set('sso-consumer.expected_host', 'tenant-a.test');
        config()->set('sso-consumer.resolver', FakeSsoUserResolver::class);
        config()->set('sso-consumer.consume_middleware', []);
        config()->set('cache.default', 'array');

        $this->artisan('sso:check')
            ->expectsOutputToContain('not safe for replay protection')
            ->assertExitCode(1);
    }

    public function test_production_rejects_file_cache_store(): void
    {
        Http::fake([
            'https://sso.test' => Http::response('', 302),
        ]);
        $this->app['env'] = 'production';
        config()->set('sso-consumer.expected_host', 'tenant-a.test');
        config()->set('sso-consumer.resolver', FakeSsoUserResolver::class);
        config()->set('sso-consumer.consume_middleware', []);
        config()->set('cache.default', 'file');
        config()->set('cache.stores.file', [
            'driver' => 'file',
            'path' => sys_get_temp_dir().'/sso-consumer-test-cache',
        ]);

        $this->artisan('sso:check')
            ->expectsOutputToContain('not safe for replay protection')
            ->assertExitCode(1);
    }

    public function test_dev_environment_allows_array_cache_store_with_warning(): void
    {
        Http::fake([
            'https://sso.test' => Http::response('', 302),
        ]);
        config()->set('sso-consumer.expected_host', 'tenant-a.test');
        config()->set('sso-consumer.resolver', FakeSsoUserResolver::class);
        config()->set('sso-consumer.consume_middleware', []);
        config()->set('cache.default', 'array');

        $this->artisan('sso:check')
            ->expectsOutputToContain('non-atomic — OK for dev only')
            ->assertExitCode(0);
    }

    public function test_parameterized_middleware_is_recognized_by_alias(): void
    {
        Http::fake([
            'https://sso.test' => Http::response('', 302),
        ]);
        // Register the alias the way Laravel does so the splitter has a real
        // alias key to find.
        $this->app['router']->aliasMiddleware('throttle', ThrottleRequests::class);
        config()->set('sso-consumer.expected_host', 'tenant-a.test');
        config()->set('sso-consumer.resolver', FakeSsoUserResolver::class);
        config()->set('sso-consumer.consume_middleware', ['throttle:sso-consume']);

        $this->artisan('sso:check')
            ->doesntExpectOutputToContain('missing: throttle');
    }

    public function test_parameterized_middleware_is_recognized_by_class_name(): void
    {
        Http::fake([
            'https://sso.test' => Http::response('', 302),
        ]);
        config()->set('sso-consumer.expected_host', 'tenant-a.test');
        config()->set('sso-consumer.resolver', FakeSsoUserResolver::class);
        config()->set('sso-consumer.consume_middleware', [ThrottleRequests::class.':sso-consume']);

        $this->artisan('sso:check')
            ->doesntExpectOutputToContain('missing: '.ThrottleRequests::class);
    }
}
