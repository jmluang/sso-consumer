<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Tests\Unit;

use Illuminate\Support\Facades\Cache;
use Jmluang\SsoConsumer\Exceptions\ReplayedTicketException;
use Jmluang\SsoConsumer\Support\JtiReplayGuard;
use Jmluang\SsoConsumer\Tests\TestCase;

class JtiReplayGuardTest extends TestCase
{
    public function test_first_claim_succeeds(): void
    {
        app(JtiReplayGuard::class)->claim('first-jti', 120);

        $this->assertTrue(Cache::has('sso_consumer:jti:first-jti'));
    }

    public function test_second_claim_for_same_jti_throws_replayed_ticket_exception(): void
    {
        $guard = app(JtiReplayGuard::class);

        $guard->claim('same-jti', 120);

        $this->expectException(ReplayedTicketException::class);

        $guard->claim('same-jti', 120);
    }

    public function test_different_jtis_do_not_conflict(): void
    {
        $guard = app(JtiReplayGuard::class);

        $guard->claim('jti-one', 120);
        $guard->claim('jti-two', 120);

        $this->assertTrue(Cache::has('sso_consumer:jti:jti-one'));
        $this->assertTrue(Cache::has('sso_consumer:jti:jti-two'));
    }

    public function test_claim_can_be_reused_after_cache_entry_is_removed(): void
    {
        $guard = app(JtiReplayGuard::class);

        $guard->claim('expired-jti', 120);
        Cache::forget('sso_consumer:jti:expired-jti');

        $guard->claim('expired-jti', 120);

        $this->assertTrue(Cache::has('sso_consumer:jti:expired-jti'));
    }
}
