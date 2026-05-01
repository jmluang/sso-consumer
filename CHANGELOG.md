# Changelog

All notable changes to `jmluang/sso-consumer` will be documented in this file.

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
