# Changelog

All notable changes to the Starmile Partner SDK are documented here. The format
follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) and the project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [6.19.0] - 2026-08-17

### Changed

- **v2 speaks item, everywhere** (pre-release shape correction — v2 has no
  consumers yet): the create body's box array is `items[]` with `item_id` on
  both request and response (the same per-box key v1 uses), the management
  paths are `/api/v2/orders/{order}/items/{item}` (`updateItem()` /
  `cancelItem()`), and the status-pool row carries `order_id` + `item_id`
  (v1: `external_parent_id` + `external_id`).

## [6.18.0] - 2026-08-16

### Added
- **`/api/v2` support — the sub_orders vocabulary** via `$client->v2()`:
  `v2()->orders()` (create / updateSubOrder / cancelSubOrder / cancel / labels),
  `v2()->statusPool()` (changes / each), `v2()->catalogue()` and `v2()->events()`.
  On v2 your per-box reference is `sub_order_id` (v1: `item_id`), the box array
  is `sub_orders[]` (v1: `parcels[]`), the create response returns our reference
  as `tracking_number` and echoes your `order_id`, and sub-orders carry **no
  Starmile tracking number of their own** — you address them by your own
  references only.
- v1 is unchanged and fully supported. The two versions are separate contracts:
  a v1 status-pool cursor is meaningless on v2 (a new cursor space) — when
  migrating, drain v2 from `since = 0` and dedupe, rather than carrying a v1
  cursor over.

## [6.17.0] - 2026-08-10

### Changed
- **Setting `merchant_tracking` late no longer fails when the package beat it to
  the hub.** If the box had already arrived under that barcode and was being held
  as an unidentified package, `updateParcel()` now MATCHES the two together
  instead of returning a server error: the parcel keeps its own tracking number,
  the reference it previously carried stays searchable, and it moves straight to
  `received_at_hub` on the status feed. Nothing changes in how you call it — send
  the tracking as usual, even if the goods arrived first.
- **A genuine `merchant_tracking` conflict is now a clean `422`.** A barcode
  already held by another of your parcels comes back as a `422` with a `message`
  and nothing is changed, instead of a `500`.

### Fixed
- `Client::VERSION` was left at `6.15.0` when 6.16.0 was released; it now tracks
  the released version again (it is what the default `User-Agent` reports).

## [6.16.0] - 2026-08-08

### Added
- **A new reason a delivery can fail: `customer_no_answer`.** Our own couriers now
  separate "nobody answered" from "we never made contact", so `reason` on the
  status feed can carry either. If you branch on `could_not_reach_customer`, add
  `customer_no_answer` alongside it — both mean the recipient was not reached, but
  only the second means contact was never established.

## [6.15.0] - 2026-08-06

### Added
- **Five more reasons a delivery can fail.** When a parcel is delivered by one of
  our delivery partners rather than our own couriers, they report failures in more
  detail than before, and `reason` on the status feed now carries that detail:
  `otp_verification_failed` (the recipient could not confirm the one-time code at
  handover), `contact_info_incorrect` (the phone number given is wrong),
  `reschedule_requested` (the recipient asked for a later day),
  `pudo_pickup_requested` (they would rather collect from a pick-up point) and
  `courier_unable_to_deliver` (the courier could not finish that day). Existing
  codes are unchanged. As always, treat an unrecognised code as "some other
  reason" — the list grows.

## [6.14.0] - 2026-07-31

### Changed
- **A package that arrived before you registered it no longer blocks its own
  order.** When goods reach the hub with no matching record they are received as
  an unidentified package under the barcode on the box. Creating the order with
  that same `merchant_tracking` used to be rejected `422` ("An order already
  exists for merchant_tracking: …") — the waiting package blocked the very order
  that would explain it. It is now accepted, and the waiting package is matched
  to the new order automatically.
- **Such a parcel starts at `received_at_hub`, not `waiting_for_arrival`.** It is
  already physically in the warehouse, so its first status reflects that. Code
  that assumes a freshly created parcel is always `waiting_for_arrival` should
  read the status rather than assume it.

### Notes
- No SDK signature change. Nothing to do differently: send the order as usual,
  even when the goods arrived first.
- Ordinary duplicates are unchanged — reusing an `item_id` or a
  `merchant_tracking` that belongs to another order is still rejected `422`.

## [6.13.0] - 2026-07-31

### Added
- **Creating an order is now idempotent on your `order_id`.** Re-sending an
  `order_id` you already used no longer fails: nothing is created and the
  original order is replayed — HTTP `200` instead of `201`, with the same
  `order_id`, `region_status` and `items`. This is the fallback for a retry after
  a timeout or a double submit, where the first attempt was accepted but you
  never saw the response.
- **`data.duplicate`** on the create response — `true` when the response replays
  an order that already existed for the `order_id` sent, `false` on a normal
  create. Present on both outcomes, so a client that ignores the status code
  still reads the right ids.

### Changed
- **A re-sent `order_id` is no longer a `422`.** Previously it was rejected with
  "An order already exists with order_id: …". Code that treats that message as a
  terminal error can drop the special case; a `ValidationException` is no longer
  raised for it. Per-parcel uniqueness is unchanged: reusing an `item_id` or a
  `merchant_tracking` under a DIFFERENT order is still rejected `422`.

### Notes
- No SDK signature change — `Orders::create()` returns the same array, now with
  the extra `duplicate` key. Check it (or the HTTP status) when you need to know
  whether the call created the order or replayed one.

## [6.12.0] - 2026-07-29

### Changed
- **An order's status feed now opens with the status it was created in.** The
  create milestone (typically `waiting_for_arrival`) was never published, so a
  feed silently began mid-journey — at whatever hub event came first, making a
  parcel look as though it materialised already in transit. It is now the first
  row for every new order, carrying `previous_status: null`.
- **`previous_status` is nullable.** It is `null` on that first row, which has
  nothing before it. Parcel-scoped rows already behaved this way.

### Fixed
- **The create status can no longer be republished as a later event.** With no
  genesis row, the pool's once-per-(order, status) guard had nothing to match, so
  a stray write back to `waiting_for_arrival` could be published as the NEWEST
  change — a parcel appearing to un-arrive after a customs hold. Emitting the
  create row closes that.

### Notes
- No SDK code change: rows are passed through verbatim. Purely additive for
  integrations that drain by cursor — you will simply see one extra, earlier row
  per new order.
- **Forward-only.** Cursors are append-only, so orders created before this could
  not be repaired retroactively: inserting their create row now would place it
  *after* `delivered`. Existing feeds are unchanged.

## [6.11.0] - 2026-07-28

### Added
- **Status pool rows explain WHY a change happened.** Each row in
  `GET /api/v1/partner/changes` now carries `reason` — a stable code from the new
  `Enum\Reason` catalogue — and `reason_detail`, the free text a person wrote
  alongside it. So a `customs_hold` arrives as `missing_declaration` and a
  `delivery_failed` as `customer_absent`, instead of a bare status you have to ask
  us about. Codes cover customs holds, failed deliveries and cancellations.
- `Enum\Reason` — the reason codes as constants, so you compare against names
  rather than magic strings.

### Notes
- **Both fields are frequently `null`** — most changes (a parcel received at a hub)
  simply have no why. Never assume a reason is present.
- **The catalogue grows.** Codes are permanent — never renamed, never reused — but
  new ones are added over time. Treat an unrecognised code as "some other reason"
  and fall back to `reason_detail` rather than failing.
- **Reporting events:** where an event's `data` carries a `reason`, sending one of
  these codes publishes it on the merchant's feed as a code they can act on
  automatically. Any other wording is passed through untouched as free text — you
  never have to force your own vocabulary into ours.
- Purely additive: existing integrations that ignore the two fields are unaffected.

## [6.10.1] - 2026-07-24

### Changed
- **`labelByOrderId()` prints a single ORDER label, not one per parcel.** The
  `order_id` form of `GET /api/v1/orders/label` now returns one order-level label
  whose barcode, weight, dimensions and contents are all the order's (contents
  aggregated across its parcels), rather than a per-parcel document. No code change
  to the SDK call — only the rendered PDF and the method's documentation.

## [6.10.0] - 2026-07-24

### Added
- **Print a whole order's labels in one call.** `orders()->labelByOrderId($orderId)`
  fetches `GET /api/v1/orders/label?order_id={ref}` and returns one PDF carrying a
  label per parcel of the order (addressed by the order's tracking number). The
  existing `label()` (by `merchant_tracking`) and `labelByParcelId()` still return a
  single parcel's label.

## [6.9.0] - 2026-07-24

### Added
- **Filter the status pool by your own reference.** `statusPool()->changes()` and
  `each()` take a new optional `$externalParentId` argument
  (`GET /api/v1/partner/changes?external_parent_id={ref}`) that narrows the feed to
  a single partner reference — the `external_parent_id` you sent on create — so you
  can track an order by your own id without ever holding our `tracking_number`. It
  composes with the cursor and may be combined with `$trackingNumber`. Unknown
  references return an empty page, not an error.

## [6.8.0] - 2026-07-17

### Changed
- **`POST /orders` now requires at least one customer contact.** An order must
  carry `customer_phone` **or** `customer_email` (previously both were optional) —
  the customer has to be reachable for delivery coordination and any government-ID
  (FIN) request. Sending neither is rejected `422` with validation errors on both
  fields. Use `recipient(name, phone, email, govId)` (or `customerPhone()` /
  `customerEmail()`) and supply at least one of phone/email.

## [6.7.0] - 2026-07-09

### Added
- **`timezone` on status pool changes.** Each change row in
  `statusPool()->changes()` now carries `timezone` — the IANA zone (e.g. `UTC`)
  that `occurred_at` is expressed in.

### Changed
- **`occurred_at` on status pool changes is now a plain `Y-m-d H:i:s` timestamp**
  (e.g. `2026-06-20 09:14:00`) instead of an ISO-8601 string with a trailing `Z`.
  The zone moved to the new `timezone` field. If you parse `occurred_at`, read it
  together with `timezone` (do not assume a `Z`/UTC suffix on the string).

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
