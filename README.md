# Starmile Partner SDK (PHP)

Official PHP SDK for the **Starmile Partner API** — the partner-facing integration
surface of the Starmile cross-border logistics platform. It wraps every Partner
API capability behind a small, typed, dependency-free client: catalogue lookup,
order intake and management, the pull-based status pool, and inbound lifecycle
events.

- **No runtime dependencies** beyond `ext-curl` and `ext-json` — installs cleanly
  on legacy stacks.
- **Wide PHP support: 7.1 → 8.4.**
- **Automatic OAuth2** — tokens are fetched, cached, and refreshed for you.
- **Typed exceptions** mapped from HTTP status codes.
- **Fluent builders** for orders, parcels, products, and events, with local
  validation of event types and fields before anything hits the network.

> This package is a thin, faithful client over the HTTP API. The HTTP API remains
> the canonical contract; the public reference lives at
> [api.starmile.app](https://api.starmile.app).

## Installation

```bash
composer require starmile/partner-sdk
```

Requires PHP **7.1+** with the `curl` and `json` extensions.

## Authentication

The Partner API uses **OAuth2 client credentials**. Your organization's back office
issues you a `client_id` and `client_secret` (a Partner API credential). The SDK
exchanges them for a short-lived bearer token at `POST /oauth/token` and attaches
it to every call — you never touch the token endpoint directly.

```php
use Starmile\PartnerSdk\Client;

$starmile = Client::create(
    getenv('STARMILE_CLIENT_ID'),
    getenv('STARMILE_CLIENT_SECRET')
);
```

Keep the `client_secret` server-side. Read it (and the base URL) from the
environment — never hardcode credentials or per-environment hosts.

### Configuration options

`Client::create($clientId, $clientSecret, $options)` accepts:

| Option            | Default                     | Description                                                        |
| ----------------- | --------------------------- | ------------------------------------------------------------------ |
| `base_url`        | `https://api.starmile.app`  | API host. Point at your sandbox/staging outside production.        |
| `scopes`          | all granted                 | Subset of the credential's scopes to request on the token.         |
| `http_client`     | bundled cURL client         | Any `HttpClientInterface` (Guzzle/Symfony/PSR-18 adapter, a mock). |
| `token_storage`   | in-memory                   | A `TokenStorageInterface` to share one token across processes.     |
| `verify_tls`      | `true`                      | TLS verification (keep on in production).                          |
| `connect_timeout` | `10`                        | Connection timeout (seconds).                                      |
| `timeout`         | `30`                        | Total request timeout (seconds).                                   |
| `user_agent`      | `starmile-partner-sdk-php/x`| Override the `User-Agent`.                                         |
| `max_attempts`    | `3`                         | Total attempts for **safe (GET)** calls on transient failure. `1` disables auto-retry. |
| `retry_base_delay_ms` | `200`                   | Base backoff between retries (exponential + jitter).              |
| `retry_max_delay_ms`  | `5000`                  | Cap on the computed backoff.                                      |

## Capabilities

The Partner API surface is reached through four resource groups, each gated by the
scopes on your credential.

| Resource                | Scope(s)                                                          | Endpoints |
| ----------------------- | ---------------------------------------------------------------- | --------- |
| `$starmile->catalogue()`| `catalogue:read`                                                 | `GET /api/v1/services`, `GET /api/v1/rates` |
| `$starmile->orders()`   | `orders:create`, `orders:update`, `orders:cancel`                | `POST /api/v1/orders`, `PATCH /api/v1/orders/{order}/parcels/{parcel}`, `POST /api/v1/orders/{order}/cancel` |
| `$starmile->statusPool()`| `status:read`                                                   | `GET /api/v1/partner/changes` |
| `$starmile->events()`   | `events:transport`, `events:pudo`, `events:customs`, `leg:handoff`| `POST /api/v1/partner/events` |

### Catalogue

```php
$services = $starmile->catalogue()->services(); // valid service_id values to order against
$rates    = $starmile->catalogue()->rates();    // the rates bound to your partner
```

### Orders

Build an order with the fluent builders (or pass a raw array matching the API
body). The corridor and delivery type come from the **Service**; you do not send a
rate — Starmile resolves the billing rate automatically.

```php
use Starmile\PartnerSdk\Builder\OrderBuilder;
use Starmile\PartnerSdk\Builder\ParcelBuilder;
use Starmile\PartnerSdk\Builder\ProductBuilder;

$order = OrderBuilder::make($serviceId, 'ORD-1001')   // service_id + your order_id
    ->recipient('Jane Doe', '+994500000000', 'jane@example.com', '5AB12C3')  // 4th arg = gov_id (AZ FIN / passport)
    ->deliverToPudo(42)                                // or ->deliverHome($regionId) / ->deliverToLocker($lockerId)
    ->shippingCost(9.90)
    ->addParcel(
        ParcelBuilder::make('ITEM-1')                // your per-item reference
            ->merchantTracking('BARCODE-1')            // the physical sticker code
            ->weightGrams(1200)
            ->addProduct(
                ProductBuilder::make('Running shoes')
                    ->hsCode('640299')
                    ->declaredValue(59.99, 'USD')
                    ->quantity(1)
            )
    );

$created = $starmile->orders()->create($order);
echo $created['tracking_number'];
```

Orders and parcels are addressed by **your own references** afterwards — the
`order_id` you sent, and a shipment's `item_id`:

```php
// Update a shipment that has not been received yet (partial; `products` replaces the list).
$starmile->orders()->updateParcel('ORD-1001', 'ITEM-1', [
    'weight_grams' => 1500,
    'merchant_tracking' => 'BARCODE-1B',
]);

// Cancel an order while it is still pre-custody (409 once in custody).
$starmile->orders()->cancel('ORD-1001', 'customer changed mind');
```

### Parcel labels (PDF)

Download a SINGLE parcel's label as a PDF, rendered from your organization's default
parcel label template. Address the parcel by its `merchant_tracking` (barcode) or its
`parcel_id` (the Starmile tracking number returned on create). The method returns the
raw PDF bytes. Scope: `labels:read`.

```php
// By merchant_tracking (barcode).
file_put_contents('label.pdf', $starmile->orders()->label('BARCODE-1'));

// By parcel_id (the Starmile tracking number).
$pdf = $starmile->orders()->labelByParcelId('STM0000000069');
```

### Status pool (replaces webhooks)

Poll the pool with the cursor you last processed; you receive every change after
it, in order. Persist the returned cursor to resume next time.

```php
// One page at a time:
$page = $starmile->statusPool()->changes($since = 0, $limit = 100);
foreach ($page->changes() as $change) {
    // $change['cursor'], ['tracking_number'], ['external_parent_id'], ['status'], ['previous_status'], ['occurred_at']
}
$next = $page->nextCursor();
$more = $page->hasMore();

// Or drain everything, auto-paging:
foreach ($starmile->statusPool()->each($since = 0) as $change) {
    $since = $change['cursor']; // persist this
}
```

### Inbound events

Report a lifecycle event for a package you handle as a carrier (`shipment.*`),
PUDO point (`parcel.*`), customs broker (`customs.*`), or foreign organization on
the next leg (`leg.*`). The `EventBuilder` validates the type and its `data` fields
locally — an unknown type or field throws before a request is made.

```php
use Starmile\PartnerSdk\Enum\EventType;

$outcome = $starmile->events()->reportEvent(
    EventType::SHIPMENT_OUT_FOR_DELIVERY,
    $trackingNumber,
    'evt-0001',                              // your idempotency key
    ['driver' => 'Driver A', 'eta' => '2026-06-28T09:00:00Z']
);
// $outcome['result'], $outcome['order_status']
```

Each accepted event advances the package. An event that is not a legal next step
for the package's current status is rejected with a `422` carrying an `error` and a
`hint` (see error handling below).

The recognised event types, their scope, and their `data` fields are exposed on
the `EventType` enum:

```php
EventType::all();                                     // every recognised type
EventType::scopeFor(EventType::CUSTOMS_HELD);         // 'events:customs'
EventType::dataFieldsFor(EventType::SHIPMENT_DELIVERED); // ['note','recipient_name','signed_by','proof_of_delivery']
```

## Error handling

Non-2xx responses raise a typed exception; all extend `StarmileException`.

| Exception                  | HTTP  | Meaning                                              |
| -------------------------- | ----- | ---------------------------------------------------- |
| `AuthenticationException`  | 401   | Missing/expired/invalid token or bad credentials.    |
| `AuthorizationException`   | 403   | Credential lacks the scope, is revoked, or API off.  |
| `NotFoundException`        | 404   | Unknown resource (or another partner's).             |
| `ConflictException`        | 409   | No longer changeable (shipment received / in custody).|
| `ValidationException`      | 422   | Validation failed; field errors + event `hint`.      |
| `RateLimitException`       | 429   | Too many requests; carries `getRetryAfter()`.        |
| `ApiException`             | other | Any other non-2xx; base for the above.               |
| `TransportException`       | —     | Network failure (no response).                       |

```php
use Starmile\PartnerSdk\Exception\ValidationException;
use Starmile\PartnerSdk\Exception\RateLimitException;
use Starmile\PartnerSdk\Exception\StarmileException;

try {
    $starmile->orders()->create($order);
} catch (ValidationException $e) {
    $e->errors();      // ['service_id' => ['The service id field is required.'], ...]
    $e->allMessages(); // flat list
    $e->getHint();     // event rejections include a "how to fix" hint
} catch (RateLimitException $e) {
    sleep($e->getRetryAfter() ?: 1);
} catch (StarmileException $e) {
    // any other failure
    $e->getMessage();
}
```

## Retries & resilience

By default the SDK retries **safe (GET)** requests on transient failures —
network errors, `429`, and `5xx` — with exponential backoff + jitter, honoring a
`Retry-After` header. Non-idempotent writes (`POST /orders`, `POST /partner/events`)
are **never** retried automatically, so a flaky response can't create a duplicate
order. Tune or disable this with `max_attempts` (see options above).

When you *do* want a write retried, opt in per call with `retry()` — mirroring
Laravel's HTTP client. It returns a one-off client; the original is unchanged:

```php
// Retry this create up to 3 times (writes included, because you asked):
$starmile->retry(3, 200)->orders()->create($order);

// Custom decision — also retry a specific conflict:
use Starmile\PartnerSdk\Exception\RateLimitException;

$starmile
    ->retry(4, 200, fn ($e) => $e instanceof RateLimitException || $e->getStatusCode() === 409)
    ->events()->report($event);
```

When a failure can't be decoded as JSON (e.g. a gateway's HTML `502`), the raw
body is preserved on the exception via `getRawBody()`:

```php
catch (\Starmile\PartnerSdk\Exception\ApiException $e) {
    $e->getResponseBody(); // [] when the body wasn't JSON
    $e->getRawBody();      // the original "<html>...502 Bad Gateway..." string
}
```

## Constants

The SDK ships the server vocabularies verbatim so you compare against names, not
magic strings:

- `Starmile\PartnerSdk\Enum\Scope` — every OAuth2 scope.
- `Starmile\PartnerSdk\Enum\EventType` — every inbound event type (+ scope/field maps).
- `Starmile\PartnerSdk\Enum\OrderStatus` — every status seen in the pool.
- `Starmile\PartnerSdk\Enum\PackageType` — `fragile` / `breakable` / `liquid`.
- `Starmile\PartnerSdk\Enum\DeliveryMethod` — `home` / `pudo` / `locker`.

## Custom HTTP transport

Replace the default cURL transport with anything implementing
`HttpClientInterface` (e.g. to reuse a configured Guzzle client, add retries, or
mock in tests):

```php
use Starmile\PartnerSdk\Client;
use Starmile\PartnerSdk\Http\HttpClientInterface;
use Starmile\PartnerSdk\Http\RawResponse;

final class GuzzleTransport implements HttpClientInterface
{
    public function send($method, $url, array $headers = [], $body = null)
    {
        // ... call Guzzle, then:
        return new RawResponse($statusCode, $responseHeaders, $responseBody);
    }
}

$starmile = Client::create($id, $secret, ['http_client' => new GuzzleTransport()]);
```

## Token sharing across processes

By default the token lives in process memory. Implement `TokenStorageInterface`
(backed by APCu, Redis, a PSR-16 cache, or a file) and pass it as `token_storage`
to reuse one token across requests/workers and avoid re-hitting `/oauth/token`.

## Testing

```bash
composer install
composer test
```

The suite drives the client through a scripted in-memory transport — no network.

## Versioning & support

Semantic Versioning. New Partner API capabilities are added here in lockstep with
the server; see the [CHANGELOG](CHANGELOG.md).

## License

MIT — see [LICENSE](LICENSE).
