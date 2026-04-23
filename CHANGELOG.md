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

### Not yet implemented (stubs with TODO)
- `TicketVerifier::verify()` — RS256 + claim checks.
- `JtiReplayGuard::claim()` — cache-based one-time-use guard.
- `ConsumeController::__invoke()` — end-to-end handling.
- `sso:check` command.
