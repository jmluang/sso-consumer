<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Jmluang\SsoConsumer\Contracts\SsoUserResolver;

class FakeSsoUserResolver implements SsoUserResolver
{
    public function findByPhone(string $phone, array $claims, Request $request): ?Authenticatable
    {
        return null;
    }

    public function findByEmail(string $email, array $claims, Request $request): ?Authenticatable
    {
        return null;
    }

    public function login(Authenticatable $user, array $claims, Request $request): void
    {
        // no-op default for tests
    }
}
