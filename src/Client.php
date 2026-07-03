<?php

namespace Starmile\PartnerSdk;

use Starmile\PartnerSdk\Auth\TokenManager;
use Starmile\PartnerSdk\Resource\Catalogue;
use Starmile\PartnerSdk\Resource\Events;
use Starmile\PartnerSdk\Resource\Orders;
use Starmile\PartnerSdk\Resource\StatusPool;
use Starmile\PartnerSdk\Retry\RetryPolicy;

/**
 * The entry point to the Starmile Partner API.
 *
 * <code>
 * $starmile = Starmile\PartnerSdk\Client::create('client_id', 'client_secret');
 *
 * $services = $starmile->catalogue()->services();
 * $order    = $starmile->orders()->create($builder);
 * $page     = $starmile->statusPool()->changes($since);
 * $starmile->events()->reportEvent(EventType::SHIPMENT_DELIVERED, $tracking, $eventId);
 * </code>
 *
 * The OAuth2 client-credentials token is obtained, cached, and refreshed for you;
 * you never touch /oauth/token directly. Every capability of the Partner API is
 * reachable from one of the four resource groups below.
 */
final class Client
{
    /** The SDK version (sent in the User-Agent). */
    const VERSION = '6.0.0';

    /** @var Configuration */
    private $configuration;

    /** @var Connection */
    private $connection;

    /** @var TokenManager */
    private $tokenManager;

    /** @var Catalogue|null */
    private $catalogue;

    /** @var Orders|null */
    private $orders;

    /** @var StatusPool|null */
    private $statusPool;

    /** @var Events|null */
    private $events;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;

        $this->tokenManager = new TokenManager(
            $configuration->getHttpClient(),
            $configuration->getBaseUrl(),
            $configuration->getCredentials(),
            $configuration->getTokenStorage()
        );

        $this->connection = new Connection(
            $configuration->getHttpClient(),
            $this->tokenManager,
            $configuration->getBaseUrl(),
            $configuration->getUserAgent(),
            $configuration->getRetryPolicy(),
            $configuration->getSleeper()
        );
    }

    /**
     * Convenience factory.
     *
     * @param array<string, mixed> $options See {@see Configuration}.
     * @return Client
     */
    public static function create($clientId, $clientSecret, array $options = array())
    {
        return new self(new Configuration($clientId, $clientSecret, $options));
    }

    /**
     * Return a clone of this client whose NEXT call retries on transient failure
     * — mirroring Laravel's HTTP client `retry()`. Unlike the safe-only default,
     * this opt-in retries ANY method, including writes, because you asked for it:
     *
     * <code>
     * $starmile->retry(3, 200)->orders()->create($order);
     * // custom decision — also retry a specific conflict:
     * $starmile->retry(4, 200, fn ($e) => $e instanceof RateLimitException || $e->getStatusCode() === 409)
     *          ->events()->report($event);
     * </code>
     *
     * The original client is unchanged; chain `retry()` only where you want it.
     *
     * @param int           $times   Total attempts (e.g. 3 = up to 2 retries).
     * @param int           $sleepMs Base backoff in milliseconds (exponential + jitter).
     * @param callable|null $when    Optional fn(StarmileException $e, int $attempt): bool
     *                               deciding whether to retry; replaces the default rule.
     * @return Client
     */
    public function retry($times, $sleepMs = 100, ?callable $when = null)
    {
        $clone = clone $this;
        $clone->connection = $this->connection->withRetryPolicy(RetryPolicy::custom($times, $sleepMs, $when));
        $clone->catalogue = null;
        $clone->orders = null;
        $clone->statusPool = null;
        $clone->events = null;

        return $clone;
    }

    /**
     * Read-only catalogue: services and rates (scope: catalogue:read).
     *
     * @return Catalogue
     */
    public function catalogue()
    {
        if ($this->catalogue === null) {
            $this->catalogue = new Catalogue($this->connection);
        }

        return $this->catalogue;
    }

    /**
     * Order intake, parcel updates, and cancellation
     * (scopes: orders:create / orders:update / orders:cancel).
     *
     * @return Orders
     */
    public function orders()
    {
        if ($this->orders === null) {
            $this->orders = new Orders($this->connection);
        }

        return $this->orders;
    }

    /**
     * The pull-based status pool (scope: status:read).
     *
     * @return StatusPool
     */
    public function statusPool()
    {
        if ($this->statusPool === null) {
            $this->statusPool = new StatusPool($this->connection);
        }

        return $this->statusPool;
    }

    /**
     * Inbound lifecycle events
     * (scopes: events:transport / events:pudo / events:customs / leg:handoff).
     *
     * @return Events
     */
    public function events()
    {
        if ($this->events === null) {
            $this->events = new Events($this->connection);
        }

        return $this->events;
    }

    /**
     * @return Configuration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * The shared token manager, e.g. to pre-warm or force-refresh the token.
     *
     * @return TokenManager
     */
    public function getTokenManager()
    {
        return $this->tokenManager;
    }
}
