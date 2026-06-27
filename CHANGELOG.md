# Changelog

All notable changes to the Starmile Partner SDK are documented here. The format
follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and the project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-06-27

### Changed
- **Recipient government ID is now `gov_id`** (was `customer_pin`) — it is an AZ FIN
  or a foreign passport. `OrderBuilder::recipient(...)`'s fourth argument now sends
  `gov_id`. Update any code that set `customer_pin` via the `set()` escape hatch.

### Added
- **`OrderBuilder::govId()`** — sets the recipient's government ID (`gov_id`) directly.

### Notes
- `OrderBuilder::consolidationRequired()` now documents that the API rejects the
  order with `422` ("Consolidation is not enabled for this service.") when the chosen
  service does not enable consolidation.

## [1.1.0] - 2026-06-27

### Added
- **Automatic retries** for safe (GET) requests on transient failures (network
  errors, `429`, `5xx`) with exponential backoff + jitter, honoring `Retry-After`.
  Configurable via `max_attempts` / `retry_base_delay_ms` / `retry_max_delay_ms`
  (`max_attempts: 1` disables). Non-idempotent writes are never auto-retried.
- **`Client::retry($times, $sleepMs, $when)`** — fluent, per-call opt-in that
  retries any call (writes included), with an optional custom decision callback,
  mirroring Laravel's HTTP client. Returns a one-off client; the original is
  unchanged.
- **`ApiException::getRawBody()`** — preserves the undecoded response body so a
  non-JSON error (e.g. a gateway's HTML `502`) stays inspectable.
- Pluggable `Sleeper` (default `RealSleeper`) so retry backoff is testable.

## [1.0.0] - 2026-06-27

Initial release. Full coverage of the Partner API surface as it stands today.

### Added
- OAuth2 client-credentials authentication with automatic token caching and
  refresh (transparent re-auth on a `401`).
- **Catalogue** (`catalogue:read`): list services and rates.
- **Orders** (`orders:create` / `orders:update` / `orders:cancel`): create an
  order, update a not-yet-received shipment, cancel a pre-custody order — with
  fluent `OrderBuilder`, `ShipmentBuilder`, and `ProductBuilder` helpers.
- **Status pool** (`status:read`): cursor-paged `changes()` plus an auto-paging
  `each()` generator.
- **Inbound events** (`events:transport` / `events:pudo` / `events:customs` /
  `leg:handoff`): report lifecycle events with an `EventBuilder` that validates
  the event type and its `data` fields locally before sending.
- Typed exceptions per HTTP status (`AuthenticationException`,
  `AuthorizationException`, `NotFoundException`, `ConflictException`,
  `ValidationException`, `RateLimitException`) plus `TransportException`.
- Dependency-free default transport (`ext-curl`); pluggable `HttpClientInterface`.
- Constant catalogues for `Scope`, `EventType`, `OrderStatus`, `PackageType`,
  and `DeliveryMethod`, mirroring the server contract verbatim.
- Supports PHP 7.1 through 8.4.
