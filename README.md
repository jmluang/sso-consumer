# jmluang/sso-consumer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/jmluang/sso-consumer.svg?style=flat-square)](https://packagist.org/packages/jmluang/sso-consumer)
[![Tests](https://img.shields.io/github/actions/workflow/status/jmluang/sso-consumer/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/jmluang/sso-consumer/actions?query=workflow%3Arun-tests+branch%3Amain)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg?style=flat-square)](LICENSE.md)

A Laravel consumer package for an upstream SSO portal. Verifies portal-signed JWT tickets and bridges upstream identity to the consuming app's local admin auth.

The companion portal application signs the tickets; integrators receive the architecture and contract specs separately. The key pieces (JWT claims v1/v2, error codes) are mirrored below for consumers.

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
SSO_PORTAL_URL=https://sso.example.com
SSO_SYSTEM_CODE=your-system-code     # must match tenant_registry.system_code on the portal
SSO_EXPECTED_HOST=admin.example.com  # required in production — see "Production Hardening" below
SSO_PORTAL_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"
```

> The `SSO_PORTAL_PUBLIC_KEY` value **must be wrapped in double quotes** so phpdotenv interprets the `\n` escapes as real newlines. Single-quoted or unquoted values will be passed to OpenSSL with literal `\n`, and signature verification will silently fail.

Then point the resolver to your implementation in `config/sso-consumer.php`:

```php
'resolver' => \App\Sso\AppSsoUserResolver::class,
```

A full integration guide is distributed with the portal application; ask your portal admin for it if you need the end-to-end setup.

## How it works

```
Portal (signs RS256 ticket)
   │  302 → https://{tenant_domain}/admin-app/sso/consume?ticket=<jwt>
   ▼
This package's ConsumeController:
   1. Verify JWT (signature, alg, exp, iss, v, aud, tenant_domain)
   2. Claim jti in cache (one-time-use guard)
   3. Resolve a local user via SsoUserResolver::findByPhone()/findByEmail()
   4. Call SsoUserResolver::login($user, $claims, $request)
   5. Dispatch SsoLoginSucceeded → 302 to success_redirect
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
| `tenant_domain` | string | ✓ | Must equal `SSO_EXPECTED_HOST` when configured; outside production, falls back to the request host with port |
| `tenant_id` | int | ✓ | |
| `tenant_system` | string | ✓ | Same as `aud` |
| `jti` | string | ✓ | 32 hex chars, one-time-use |
| `v` | int | ✓ | `2` for phone-primary tickets, `1` for legacy email tickets |
| `iat` / `exp` | int | ✓ | 120s TTL recommended |
| `nbf` | int | optional | |

## Error Codes

Rendered on the error page and emitted via `SsoLoginFailed` events.

`ticket_missing`, `ticket_invalid`, `ticket_expired`, `ticket_replayed`, `ticket_version_unsupported`, `audience_mismatch`, `tenant_mismatch`, `user_not_found`, `identity_conflict`, `resolver_failed`

## The `SsoUserResolver` contract

The package does **not** touch `Auth` or `session` directly — that's your
job inside `login()`. The package **does** orchestrate the phone/email
lookups and detects conflicts, so a careless implementation can no longer
silently log an attacker into the wrong account.

You implement three primitives:

```php
namespace App\Sso;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Jmluang\SsoConsumer\Contracts\SsoUserResolver;
use Jmluang\SsoConsumer\Support\PhoneNormalizer;

class AppSsoUserResolver implements SsoUserResolver
{
    public function findByPhone(string $phone, array $claims, Request $request): ?Authenticatable
    {
        // Normalize both sides so domestic ("15912340001") and international
        // ("+852 91234567") tickets match local rows regardless of how the
        // number was originally typed. See "Phone format" below.
        $normalized = PhoneNormalizer::normalize($phone);

        return AdminUser::query()->where('phone', $normalized)->first();
    }

    public function findByEmail(string $email, array $claims, Request $request): ?Authenticatable
    {
        return AdminUser::query()->where('email', $email)->first();
    }

    public function login(Authenticatable $user, array $claims, Request $request): void
    {
        Auth::guard('admin')->login($user);
        $request->session()->regenerate();
        // Update last_login_at, fire app-specific events, etc.
    }
}
```

The library will:

1. Call `findByPhone()` if (and only if) the verified ticket carries a non-empty phone claim.
2. Call `findByEmail()` if (and only if) the verified ticket carries a non-empty email claim.
3. Throw `IdentityConflictException` (error code `identity_conflict`) if both lookups succeed but return users with different identifiers — `login()` is **not** called.
4. Throw `UserNotFoundException` if both lookups return `null`.
5. Otherwise call `login($user, $claims, $request)` exactly once.

## Phone format

The portal issues `phone` claims in one of two canonical shapes:

- **Domestic** — digits only, e.g. `15912340001`.
- **International** — `+<country> <local>`, separated by a single space,
  e.g. `+852 91234567`. The country code is 1–4 digits; the local number is
  3–20 digits with no inner separators.

Use `Jmluang\SsoConsumer\Support\PhoneNormalizer::normalize($phone)` on both
the inbound claim and the locally stored column before comparing — operators
typing `159-1234-0001`, `(415) 555-0123`, or `+852-9123-4567` all collapse to
the canonical form, so lookups don't miss because of formatting drift. The
helper returns `null` for empty input and throws `InvalidArgumentException`
for inputs that can't be parsed (letters, too few digits, missing separator
between country code and local number, etc.).

If your local column already stores normalized values, you only need to
normalize the inbound claim. If you're migrating an existing column, run the
helper during the backfill described below.

## Upgrading To Phone-Primary Tickets

Before enabling portal-issued v2 tickets:

1. Add a normalized phone column to the consuming app's admin user table.
2. Backfill existing admin users from the trusted upstream SSO phone value.
3. Implement `findByPhone()` using the normalized column, and `findByEmail()` for legacy rows.
4. Deploy the resolver before switching the portal kill-switch from v1 to v2.
5. Monitor `user_not_found`, `identity_conflict`, and resolver failures during the rollout window.

## Production Hardening

The defaults are safe to use in development, but a production deployment **must** review the following:

1. **`SSO_EXPECTED_HOST` is required.** In production, consume requests fail if this value is missing; `php artisan sso:check` also reports the misconfiguration. Outside production, the consumer can fall back to the request host with port for local testing.
2. **`replay_cache_store` must be a shared, atomic cache** (Redis, Memcached, or Database). The `array` driver gives each PHP worker its own memory, which silently disables replay protection. The `file` driver is not atomic. `php artisan sso:check` enforces this in production.
3. **The consume route is rate-limited by default** (`throttle:sso-consume`). Each request triggers an RSA signature verification, which is CPU-expensive — without throttling the endpoint is a DoS amplifier. The package registers a default `sso-consume` limiter at 60 requests/minute per IP; override it in `App\Providers\AppServiceProvider::boot()` when your app needs tenant-aware or user-aware limits:
   ```php
   RateLimiter::for('sso-consume', fn (Request $request) =>
       Limit::perMinute(60)->by($request->ip()));
   ```
   Override `consume_middleware` if your app already has a tenant-aware throttle.
4. **HTTPS enforcement depends on trusted proxy configuration.** Production consume requests must be HTTPS. If TLS terminates at a load balancer or reverse proxy, configure Laravel trusted proxies so `$request->isSecure()` honors `X-Forwarded-Proto: https`; otherwise the package will correctly reject the internal plaintext hop as `ticket_invalid`.
5. **`SsoLoginFailed` events carry the full claim array**, including `phone`, `email`, and `name`. Listeners that ship to log aggregators or alerting systems should redact or hash PII before forwarding.
6. **Octane / Swoole / RoadRunner caveat.** The verifier mutates the static `Firebase\JWT\JWT::$leeway` while decoding. Concurrent requests sharing a worker process can race on this state. Pin a single value via config and avoid hot-reloading it, or run under traditional php-fpm if this is a concern.

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
