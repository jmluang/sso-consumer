<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Support;

class JtiReplayGuard
{
    /**
     * Atomically claim a jti. First caller wins; subsequent calls throw.
     *
     * Implementation:
     *   Cache::store(config('sso-consumer.replay_cache_store'))
     *        ->add($prefix . $jti, 1, $ttlSeconds);
     *   add() returns false if key already exists → throw ReplayedTicketException.
     *
     * TTL = exp - now (seconds). After that the cache entry expires and the
     * ticket could in theory be replayed — but it's already past exp, so the
     * TicketVerifier will reject it earlier.
     *
     * TODO(OpenCode): implement.
     */
    public function claim(string $jti, int $ttlSeconds): void
    {
        throw new \LogicException('JtiReplayGuard::claim not implemented yet.');
    }
}
