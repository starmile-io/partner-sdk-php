# Changelog

All notable changes to the Starmile Partner SDK are documented here. The format
follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and the project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [5.0.0] - 2026-07-03

### Changed
- **Update and cancel are allowed only at the order's flow first step.**
  `orders()->updateParcel()` and `orders()->cancel()` now return `409` once the
  order has moved past its flow's first step (previously "once received / in
  custody"). No SDK code change — the same calls, a slightly stricter server rule.

### Changed (BREAKING)
- **`orders()->create()` now returns only the order_id.** The create response is
  `['order_id' => 'STM…']` (the created order's Starmile tracking number) instead of
  the full order object. Use it as the `order_id` on the status, label and management
  endpoints; address parcels by your own `merchant_tracking`.
- **The parcel label endpoint is now strictly one parcel per call.**
  `orders()->label($merchantTracking)` now takes a single argument — the parcel's
  `merchant_tracking` (barcode). The previous `label($orderId, $merchantTracking)`
  signature and the whole-order (`order_id`) form are **removed**: a label is always
  for exactly one parcel.
- **Added `orders()->labelByParcelId($parcelId)`** to address the parcel by its
  Starmile tracking number (the `tracking_number` returned on create) instead.

## [4.0.1] - 2026-07-02

### Changed
- **A duplicate `merchant_tracking` on `orders()->create()` is now a clean `422`.**
  Reusing a `merchant_tracking` that already exists (or repeating one across parcels
  in the same order) is rejected with a `422` message instead of surfacing as a
  server error. Re-sending an order never creates a duplicate.
- **`orders()->label()` now returns the org's default parcel label template** —
  each parcel is rendered from the organization's configured parcel template (the
  full sticker), one page per package, instead of a bare Code-128 barcode. No code
  change is required: same method, same `labels:read` scope, same PDF bytes.

## [4.0.0] - 2026-07-02

### Changed
- **`GET /api/v1/services` now lists only the services the partner is contracted
  for** — those it holds an active bound rate on (previously every service the
  operating entity ran). Ordering a service with no bound rate is rejected `422`.
  No SDK API change; `catalogue()->services()` simply returns the scoped list.

### Added
- **`Orders::label($orderId, $merchantTracking = null)`** — download the printable
  parcel label(s) as a single PDF (one scannable Code-128 barcode per package),
  addressed by your `order_id` (every parcel) or a single parcel's
  `merchant_tracking`. Returns the raw PDF bytes. New endpoint
  `GET /api/v1/orders/label` and scope **`Scope::LABELS_READ`** (`labels:read`),
  granted to sender credentials by default. Backed by `Connection::getRaw()` for
  binary responses.

### Removed (BREAKING)
- **`OrderBuilder::rateId()` is removed.** The Partner API no longer accepts a
  partner-supplied rate on order creation — Starmile resolves the billing rate
  internally from the order context (service + partner + corridor + direction) and
  bills at the rate's configured trigger status. Drop any `->rateId(...)` call; the
  corridor and delivery type already come from the Service. A legacy `rate_id` left
  in a raw body is ignored by the server (not an error).

## [3.0.0] - 2026-06-30

### Changed (BREAKING)
- The two PUDO accept events are merged into one. **`EventType::PARCEL_ACCEPTED_LM`
  (`parcel.accepted_lm`) is removed** — send **`EventType::PARCEL_ACCEPTED`
  (`parcel.accepted`)** for any PUDO acceptance. `parcel.accepted` now also accepts
  the optional **`shelf`** field (previously only on `accepted_lm`); its full data
  set is `note`, `point_code`, `shelf`. This mirrors the server: a single
  `accepted_at_pudo` step whose hold window follows the order's service direction
  (delivery vs return), not a first-mile/last-mile split.

## [2.1.0] - 2026-06-30

### Changed
- The order-create body no longer sends a `delivery` field. The delivery channel
  (home / pudo / locker) is a property of the chosen Service (its `delivery_type`),
  not something the partner sends — pick the Service whose channel you want and
  provide the matching destination id. The fluent builder is unchanged:
  `deliverHome($regionId)` / `deliverHomeToRegion($region)` / `deliverToPudo($pudoId)`
  / `deliverToLocker($lockerId)` still set the destination id; they just no longer
  emit the redundant `delivery` key.
- `Enum\DeliveryMethod` is retained as the canonical channel vocabulary
  (`home` / `pudo` / `locker`) but is now informational only.

## [2.0.0] - 2026-06-29

### Changed (BREAKING)
- The per-item unit under an order is now a **parcel** (was "shipment"), matching
  the Partner API:
  - `ShipmentBuilder` → **`ParcelBuilder`** (same fluent API; `make($itemId)`,
    `addProduct(...)`, ...).
  - `OrderBuilder::addShipment(...)` → **`addParcel(...)`**; the create-order body
    now sends `parcels[]` (was `shipments[]`).
  - `Orders::updateShipment($orderId, $itemId, $changes)` →
    **`updateParcel(...)`**, which calls
    `PATCH /api/v1/orders/{order}/parcels/{parcel}` (was `/shipments/{shipment}`).
- Carrier transport events are **unchanged**: `EventType::SHIPMENT_*`
  (`shipment.*`) still describe the dispatch shipment handed to the carrier, and
  the `events:transport` scope is unchanged.

### Migration
- Rename `ShipmentBuilder` → `ParcelBuilder`, `addShipment` → `addParcel`,
  `updateShipment` → `updateParcel` in your integration. No change is needed for
  event reporting.

## [1.3.0] - 2026-06-29

### Added
- **`OrderBuilder::deliverHomeToRegion($region, ...)`** — deliver a Home Delivery
  order to a region given its **name** (or id). Partners usually know the
  destination region by its human name (e.g. `"Abşeron"`), not our internal id;
  the API resolves the value against the destination country by an exact name
  match first, then falls back to an id lookup, and stamps the resolved region on
  the order (this is what lets the order be priced by its destination Tier).
  `deliverHome($regionId, ...)` (id only) is unchanged.

### Notes
- The request now accepts a `region` field (name or id) alongside `region_id`
  (id only). The required-field error for a Home Delivery service is now
  `"region is required for a Home Delivery service."`

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
