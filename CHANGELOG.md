# Changelog

All notable changes to `jmluang/sso-consumer` will be documented in this file.

## v0.0.8 - 2026-05-07

### Added

- Added `SSO_EXPECTED_HOSTS` / `sso-consumer.expected_hosts` for consumers that
  receive SSO callbacks on multiple tenant domains.

### Changed

- `tenant_domain` validation now accepts either the legacy single
  `SSO_EXPECTED_HOST` value or one of the comma-separated `SSO_EXPECTED_HOSTS`
  values. Production deployments still require at least one explicit expected
  host and will not fall back to the incoming request `Host` header.
- `php artisan sso:check` now validates the normalized expected-host list, so
  single-domain consumers can keep `SSO_EXPECTED_HOST` and multi-tenant
  consumers can switch to `SSO_EXPECTED_HOSTS=tenant-a.example.com,tenant-b.example.com`.

### Upgrade Notes

- Applications using `SSO_EXPECTED_HOST` can upgrade from `v0.0.7` without
  changing their environment variables.
- Multi-tenant applications with independent callback domains should configure
  `SSO_EXPECTED_HOSTS` instead of loosening host validation.
- Applications that subclass or replace `TicketVerifier` and override
  `verify()` must update the second parameter type from `string` to
  `string|array`.

## v0.0.6 - 2026-05-01

Security Update

## v0.0.5 - 2026-05-01

phone support

## [Unreleased]

### Added

- Initial package scaffold: contracts, exceptions, events, service provider.
- Config with portal_url / system_code / public_key / resolver / replay cache settings.
- Default error view and `<x-sso-consumer::login-button>` component.
- `SsoUserResolver` contract for the consuming app's login bridge.
- `SsoLoginSucceeded` / `SsoLoginFailed` events.
- Zh_CN and en translations for error messages.
- `TicketVerifier::verify()` with RS256, leeway, and v1 claim validation.
- `JtiReplayGuard::claim()` with atomic cache `add()` replay protection.
- `ConsumeController::__invoke()` end-to-end ticket consumption.
- `sso:check` production readiness command.
- PHPUnit fixtures and tests, including RSA keys that are strictly for tests and must never be used in production ticket signing.
- PHPStan level 5 clean (LoginButton view-string fix + baseline for package-config env() false positive).
- v2 phone-primary ticket claim validation while keeping v1 email-only tickets as a legacy fallback.
- Strict required-claim and invariant checks for `sub`, `phone`, `tenant_system`, `aud`, `jti`, and timestamps.
- Library-enforced phone/email identity consistency: `IdentityConflictException`
  (error code `identity_conflict`) is raised when both claims resolve to
  different local users — the host application can no longer swallow this
  via a careless resolver implementation.

### Changed

- **BREAKING:** `SsoUserResolver` contract split into `findByPhone()`,
  `findByEmail()`, and `login()`. The library now performs the lookup
  orchestration and the conflict check itself; consumers only contribute
  the per-claim lookup primitives plus the login side-effects. Existing
  `resolve()` implementations must be migrated.
