# Changelog

All notable changes to `jmluang/sso-consumer` will be documented in this file.

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
