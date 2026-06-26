# Changelog

All notable changes to the Starmile Partner SDK are documented here. The format
follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and the project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
