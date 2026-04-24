<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Tests\Feature;

use Illuminate\Support\Facades\Http;
use Jmluang\SsoConsumer\Tests\Fixtures\FakeSsoUserResolver;
use Jmluang\SsoConsumer\Tests\TestCase;

class CheckConfigCommandTest extends TestCase
{
    public function test_ready_configuration_exits_successfully(): void
    {
        Http::fake([
            'https://protal.florentiavillage.com' => Http::response('', 302),
        ]);
        config()->set('sso-consumer.resolver', FakeSsoUserResolver::class);
        config()->set('sso-consumer.consume_middleware', []);

        $this->artisan('sso:check')
            ->expectsOutputToContain('system code')
            ->expectsOutputToContain('public key')
            ->assertExitCode(0);
    }

    public function test_empty_public_key_exits_with_failure(): void
    {
        Http::fake([
            'https://protal.florentiavillage.com' => Http::response('', 302),
        ]);
        config()->set('sso-consumer.public_key', '');
        config()->set('sso-consumer.resolver', FakeSsoUserResolver::class);

        $this->artisan('sso:check')
            ->expectsOutputToContain('public key')
            ->assertExitCode(1);
    }
}
