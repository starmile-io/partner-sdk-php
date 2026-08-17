<?php

namespace Starmile\PartnerSdk;

use Starmile\PartnerSdk\Resource\V2\Catalogue;
use Starmile\PartnerSdk\Resource\V2\Events;
use Starmile\PartnerSdk\Resource\V2\Orders;
use Starmile\PartnerSdk\Resource\V2\StatusPool;

/**
 * The /api/v2 surface — the items/sub_order vocabulary.
 *
 * <code>
 * $starmile = Starmile\PartnerSdk\Client::create('client_id', 'client_secret');
 *
 * $order = $starmile->v2()->orders()->create(array(
 *     'service_id' => 12,
 *     'order_id'   => 'PO-1001',
 *     'customer_email' => 'buyer@example.com',
 *     'items' => array(
 *         array('sub_order_id' => 'BOX-1', 'merchant_tracking' => 'MT-1',
 *               'products' => array(array('name' => 'Widget'))),
 *     ),
 * ));
 * $page = $starmile->v2()->statusPool()->changes($since);
 * </code>
 *
 * v1 and v2 are SEPARATE contracts: a v1 status-pool cursor means nothing on
 * v2 (a new id space), and the create/management bodies differ. Use one
 * version per integration; migrate deliberately, not per-call.
 */
final class V2
{
    /** @var Connection */
    private $connection;

    /** @var Catalogue|null */
    private $catalogue;

    /** @var Orders|null */
    private $orders;

    /** @var StatusPool|null */
    private $statusPool;

    /** @var Events|null */
    private $events;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
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
     * Order intake, sub-order updates, and cancellation
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
     * The v2 status pool (scope: status:read).
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
     * Inbound lifecycle events (scopes: events:* / leg:handoff).
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
}
