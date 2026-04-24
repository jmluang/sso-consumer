<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Tests;

use Illuminate\Foundation\Application;
use Jmluang\SsoConsumer\SsoConsumerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @param  Application  $app
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            SsoConsumerServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));

        // Sensible defaults for tests; individual tests can override.
        $app['config']->set('sso-consumer.system_code', 'xiaohongshu');
        $app['config']->set('sso-consumer.portal_url', 'https://protal.florentiavillage.com');
        $app['config']->set('sso-consumer.public_key', (string) file_get_contents(__DIR__.'/Fixtures/keys/test-public.pem'));
        $app['config']->set('sso-consumer.consume_path', '/admin-app/sso/consume');
        $app['config']->set('sso-consumer.consume_middleware', ['web']);
    }
}
