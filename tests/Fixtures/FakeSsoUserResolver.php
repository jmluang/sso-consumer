<?php

declare(strict_types=1);

namespace Jmluang\SsoConsumer\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Jmluang\SsoConsumer\Contracts\SsoUserResolver;

class FakeSsoUserResolver implements SsoUserResolver
{
    public function resolve(array $claims, Request $request): ?Authenticatable
    {
        return null;
    }
}
