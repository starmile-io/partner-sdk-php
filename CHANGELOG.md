# Changelog

All notable changes to the Starmile Partner SDK are documented here. The format
follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and the project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [6.6.0] - 2026-07-08

### Added
- **Cancel a single parcel.** `orders()->cancelParcel($orderId, $itemId, $reason = null)`
  cancels one package of an order (addressed by your own `order_id` + `item_id`)
  while it is still at the flow's first step — `POST /api/v1/orders/{order}/parcels/{parcel}/cancel`
  (scope `orders:cancel`). When the cancelled parcel was the order's last active
  parcel, the order is cancelled too. `409` once the parcel has been received /
  moved past the first step. Complements the existing `orders()->cancel()`.

## [6.5.0] - 2026-07-07

### Added
- **Filter the status pool by tracking number.** `statusPool()->changes()` and
  `statusPool()->each()` take an optional third argument, `$trackingNumber`, that
  narrows the feed to a single Starmile tracking number — an order (order-level
  changes) or one parcel (parcel-scoped changes). Page from `since = 0` with the
  tracking number until `hasMore()` is false to reconstruct just that subject's
  history, instead of draining and filtering the whole feed. Additive and
  backward-compatible: omit it to poll the full feed as before.

## [6.4.0] - 2026-07-07

### Changed
- **`rate.service_id` is now the Service's own id.** In `catalogue()->rates()`,
  each rate's `service_id` now equals the Service `id` from
  `catalogue()->services()` (and the `service_id` you send on order creation) —
  previously it carried the underlying flow id. This makes a rate map 1:1 to a
  single Service, so two Services sharing one flow are priced independently. If
  you correlated rates to services, match on the Service's `id` (not
  `flow_definition_id`). The order-creation contract is unchanged — you have
  always sent a Service's own `id` as `service_id`.

## [6.3.0] - 2026-07-06

### Added
- **`country` on status pool changes.** A change row in
  `statusPool()->changes()` now carries `country` — the ISO-2 country the change
  occurred in (the hub's country). On a cross-border journey this lets you tell an
  origin event (e.g. `CN`, a parcel received at the origin hub) apart from a
  destination one (e.g. `AZ`), which you previously could not resolve from the
  feed. `null` on rows that predate the field. Additive and backward-compatible:
  pollers that ignore it keep working.

## [6.2.0] - 2026-07-06

### Added
- **`external_id` on status pool changes.** A change row in
  `statusPool()->changes()` now carries `external_id` — your own reference for a
  single **parcel** (the `item_id` you sent on `orders()->create()`). It is set
  only on **parcel-scoped** changes (a change on one parcel, such as a parcel
  received at the hub); order-level changes leave it null. This lets you act on the
  exact parcel — e.g. mark just that sub-order received — instead of only the whole
  order. `external_parent_id` (your order reference) is unchanged. Additive and
  backward-compatible: existing pollers that ignore the field keep working.

## [6.1.0] - 2026-07-04

### Changed
- **An unmapped Home Delivery region is no longer rejected.** When your
  `(parent_region, region)` reference is not mapped yet, `orders()->create()` now
  succeeds (`201`) instead of returning `422`. The order is accepted and queued;
  an operator maps your region in Starmile and the waiting order is resolved
  automatically — you do NOT resend it.

### Added
- **`region_status` on the create-order response.** `orders()->create()` now
  returns a `region_status` field alongside `order_id` and `items`:
  `mapped` (region resolved), `pending_mapping` (accepted, awaiting an operator
  mapping), or `not_applicable` (PUDO / locker / clearance — no home region).
- **`Enum\RegionStatus`** constant catalogue (`MAPPED`, `PENDING_MAPPING`,
  `NOT_APPLICABLE`) mirroring the server vocabulary.

## [6.0.0] - 2026-07-03

### Changed
- **Home delivery is addressed per-partner, map-only (BREAKING).** A partner now
  addresses a Home Delivery destination by its OWN reference — the region's parent
  region NAME plus its leaf region id/code — which Starmile maps, per partner, to
  one of its regions. `OrderBuilder::deliverHome()` now takes
  `($parentRegion, $region, $addressFirst = null, $addressSecond = null, $zip = null)`
  and sends `parent_region` + `region`. The old `deliverHome($regionId)` (which
  sent `region_id`) and `deliverHomeToRegion($name)` are removed, and the server
  no longer accepts `region_id` or a fuzzy name/id lookup: an unmapped reference is
  rejected (an operator maps your regions in Starmile first). The parent
  disambiguates a leaf that repeats across parents.

## [5.0.0] - 2026-07-03

### Changed
- **Update and cancel are allowed only at the order's flow first step.**
  `orders()->updateParcel()` and `orders()->cancel()` now return `409` once the
  order has moved past its flow's first step (previously "once received / in
  custody"). No SDK code change — the same calls, a slightly stricter server rule.

### Changed
- **A parcel's `item_id` and `merchant_tracking` are now optional.**
  `ParcelBuilder::make()` takes an optional item_id; omit it and the parcel's
  `partner_tracking` is left empty. Omit `merchant_tracking` and Starmile fills it
  with the parcel's own tracking number. (The ones you DO send still must be unique.)

### Changed (BREAKING)
- **The parcel's reference fields are renamed in the JSON.** A parcel now carries
  `merchant_tracking` (the physical sticker code, formerly `barcode`) and
  `partner_tracking` (your own per-item reference — the `item_id` you send on create,
  formerly the parcel's `tracking`). The request field on `ParcelBuilder` /
  `create()` is still `item_id`; it is echoed back on the parcel as `partner_tracking`.
- **`orders()->create()` returns a trimmed response — `order_id` + `items`.** Instead
  of the full order object, the response is `['order_id' => 'STM…', 'items' => [...]]`:
  the order's Starmile tracking number, plus one entry per parcel mapping your `item_id`
  (as sent, or null) to `parcel_id` (our parcel's Starmile tracking number).
- **The parcel label endpoint is now strictly one parcel per call.**
  `orders()->label($merchantTracking)` takes a single argument — the parcel's
  `merchant_tracking`. The previous `label($orderId, $merchantTracking)` signature and
  the whole-order (`order_id`) form are **removed**: a label is always for one parcel.
- **Added `orders()->labelByParcelId($parcelId)`** to address the parcel by our
  parcel id (the `items[].parcel_id` returned on create) instead.

## [4.0.1] - 2026-07-02

### Changed
- **`orders()->create()` rejects re-used references with a clean `422`.** Your
  `order_id`, each parcel's `item_id`, and each `merchant_tracking` must be unique;
  reusing one that already exists (or repeating an item_id / merchant_tracking across
  parcels in the same order) is rejected `422`, so re-sending an order never creates a
  duplicate.
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
