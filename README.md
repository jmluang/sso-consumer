# jmluang/sso-consumer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jmluang/sso-consumer.svg?style=flat-square)](https://packagist.org/packages/jmluang/sso-consumer)
[![Tests](https://img.shields.io/github/actions/workflow/status/jmluang/sso-consumer/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/jmluang/sso-consumer/actions?query=workflow%3Arun-tests+branch%3Amain)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE.md)

A Laravel consumer package for the **fv-portal** SSO system. Verifies portal-signed JWT tickets and bridges upstream identity to the consuming app's local admin auth.

Paired with the private [`jmluang/fv-portal`](https://github.com/jmluang/fv-portal) application, which signs the tickets. Architecture and contract specs live in the portal repo under `docs/sso/`; the key pieces (JWT claims v1/v2, error codes) are mirrored below for consumers.

## Requirements

- PHP `^8.3`
- Laravel `^11.0 || ^12.0 || ^13.0`

## Installation

```bash
composer require jmluang/sso-consumer:^1.0
```

Publish the config:

```bash
php artisan vendor:publish --tag=sso-consumer-config
```

Optionally publish views & translations:

```bash
php artisan vendor:publish --tag=sso-consumer-views
php artisan vendor:publish --tag=sso-consumer-lang
```

## Configuration

Fill in `.env`:

```
SSO_PORTAL_URL=https://protal.florentiavillage.com
SSO_SYSTEM_CODE=xiaohongshu          # or `gd`, matching tenant_registry.system_code on portal
SSO_PORTAL_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"
```

Then point the resolver to your implementation in `config/sso-consumer.php`:

```php
'resolver' => \App\Sso\AppSsoUserResolver::class,
```

See [docs/sso/consumer/integration-guide.md](../fv-portal/docs/sso/consumer/integration-guide.md) (if docs live in the portal repo) for the full 10-step setup.

## How it works

```
Portal (signs RS256 ticket)
   │  302 → https://{tenant_domain}/admin-app/sso/consume?ticket=<jwt>
   ▼
This package's ConsumeController:
   1. Verify JWT (signature, alg, exp, iss, v, aud, tenant_domain)
   2. Claim jti in cache (one-time-use guard)
   3. Call your SsoUserResolver::resolve($claims, $request)
   4. Dispatch SsoLoginSucceeded → 302 to success_redirect
   On any failure → dispatch SsoLoginFailed → render error page with
                    "Return to portal" action.
```

## JWT Claims

Ticket is RS256-signed by the portal; consumer must verify using the portal's public key.

| Claim | Type | Required | Notes |
|---|---|---|---|
| `iss` | string | ✓ | Must be `sso-portal` |
| `aud` | string | ✓ | Must equal `config('sso-consumer.system_code')` |
| `sub` | string | ✓ | v2: phone. v1 legacy: email |
| `phone` | string | v2 only | Primary lookup key for v2 tickets |
| `email` | string | v1 required, v2 optional | Secondary legacy lookup key |
| `name` | string | optional | Display name from portal/upstream identity |
| `tenant_domain` | string | ✓ | Must equal `$request->getHost()` |
| `tenant_id` | int | ✓ | |
| `tenant_system` | string | ✓ | Same as `aud` |
| `jti` | string | ✓ | 32 hex chars, one-time-use |
| `v` | int | ✓ | `2` for phone-primary tickets, `1` for legacy email tickets |
| `iat` / `exp` | int | ✓ | 120s TTL recommended |
| `nbf` | int | optional | |

## Error Codes

Rendered on the error page and emitted via `SsoLoginFailed` events.

`ticket_missing`, `ticket_invalid`, `ticket_expired`, `ticket_replayed`, `ticket_version_unsupported`, `audience_mismatch`, `tenant_mismatch`, `user_not_found`, `resolver_failed`

## The `SsoUserResolver` contract

The package does **not** touch `Auth` or `session`. You implement:

```php
namespace App\Sso;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Jmluang\SsoConsumer\Contracts\SsoUserResolver;

class AppSsoUserResolver implements SsoUserResolver
{
    public function resolve(array $claims, Request $request): ?Authenticatable
    {
        $user = null;

        if (($claims['v'] ?? null) === 2 && isset($claims['phone'])) {
            $user = AdminUser::query()
                ->where('phone', $claims['phone'])
                ->first();
        }

        if (! $user && isset($claims['email'])) {
            $user = AdminUser::query()
                ->where('email', $claims['email'])
                ->first();
        }

        if (! $user) {
            return null;
        }

        Auth::guard('admin')->login($user);
        $request->session()->regenerate();

        return $user;
    }
}
```

## Upgrading To Phone-Primary Tickets

Before enabling portal-issued v2 tickets:

1. Add a normalized phone column to the consuming app's admin user table.
2. Backfill existing admin users from the trusted upstream SSO phone value.
3. Update your `SsoUserResolver` to look up by phone first, then email for legacy rows.
4. Deploy the resolver before switching the portal kill-switch from v1 to v2.
5. Monitor `user_not_found` and resolver failures during the rollout window.

## Events

- `Jmluang\SsoConsumer\Events\SsoLoginSucceeded` — `$user`, `$claims`, `$requestId`
- `Jmluang\SsoConsumer\Events\SsoLoginFailed` — `$errorCode`, `$claims?`, `$rawTicketHead?`, `$requestId`, `$exception?`

Write your own listeners for audit logging / alerting.

## Commands

```bash
php artisan sso:check    # verify config is production-ready
```

## Testing

The RSA key pair under `tests/Fixtures/keys/` is only for automated tests. Never use it to sign or verify production SSO tickets.

```bash
composer test
composer analyse
composer format
```

## Versioning

- `0.x.y` — prerelease, API may change
- `1.x.y` — semver stable
- Adding optional JWT claims → minor; removing/renaming claims → major

## License

MIT — see [LICENSE.md](LICENSE.md).
