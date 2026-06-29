<?php

namespace Starmile\PartnerSdk\Builder;

/**
 * Fluent builder for an order intake payload (POST /api/v1/orders). Required:
 * a `service_id` (one of GET /api/v1/services), an `order_id` (the partner's own
 * order reference), and at least one shipment. The corridor (origin/destination)
 * and delivery type come from the Service; `rate_id` is optional (Finance resolves
 * the rate at the invoice-trigger status).
 */
final class OrderBuilder
{
    /** @var array<string, mixed> */
    private $attributes = array();

    /** @var array<int, ShipmentBuilder|array<string, mixed>> */
    private $shipments = array();

    /**
     * @param int    $serviceId The Service entity id (drives the flow + corridor).
     * @param string $orderId   The partner's own order reference.
     */
    public function __construct($serviceId, $orderId)
    {
        $this->attributes['service_id'] = (int) $serviceId;
        $this->attributes['order_id'] = (string) $orderId;
    }

    /**
     * @return OrderBuilder
     */
    public static function make($serviceId, $orderId)
    {
        return new self($serviceId, $orderId);
    }

    /**
     * Optional Rate id (validated against the Service + credential when sent).
     *
     * @return $this
     */
    public function rateId($rateId)
    {
        $this->attributes['rate_id'] = (int) $rateId;

        return $this;
    }

    /**
     * The recipient. `govId` is the recipient's government ID (`gov_id`) — the AZ FIN
     * or a foreign passport number — used for the customs declaration.
     *
     * @return $this
     */
    public function recipient($name = null, $phone = null, $email = null, $govId = null)
    {
        if ($name !== null) {
            $this->attributes['customer_name'] = $name;
        }
        if ($phone !== null) {
            $this->attributes['customer_phone'] = $phone;
        }
        if ($email !== null) {
            $this->attributes['customer_email'] = $email;
        }
        if ($govId !== null) {
            $this->attributes['gov_id'] = $govId;
        }

        return $this;
    }

    /**
     * The recipient's government ID (`gov_id`) — AZ FIN or foreign passport.
     *
     * @return $this
     */
    public function govId($govId)
    {
        $this->attributes['gov_id'] = $govId;

        return $this;
    }

    /**
     * Deliver to a region by its id (Home Delivery services). Optionally set the
     * address lines. Use {@see deliverHomeToRegion()} when you only know the region
     * NAME — the API resolves a name to its id for you.
     *
     * @return $this
     */
    public function deliverHome($regionId, $addressFirst = null, $addressSecond = null, $zip = null)
    {
        $this->attributes['delivery'] = 'home';
        $this->attributes['region_id'] = (int) $regionId;

        return $this->address($addressFirst, $addressSecond, $zip);
    }

    /**
     * Deliver to a region by its NAME or id (Home Delivery services). The API
     * resolves the value against the destination country by an exact name match
     * first, then falls back to an id lookup — so a partner that knows the region
     * only by its human name (e.g. "Abşeron") can send it as-is. Optionally set the
     * address lines.
     *
     * @param  string|int  $region  the destination region name (preferred) or id
     * @return $this
     */
    public function deliverHomeToRegion($region, $addressFirst = null, $addressSecond = null, $zip = null)
    {
        $this->attributes['delivery'] = 'home';
        $this->attributes['region'] = (string) $region;

        return $this->address($addressFirst, $addressSecond, $zip);
    }

    /**
     * Deliver to a PUDO point (Pudo Delivery services).
     *
     * @return $this
     */
    public function deliverToPudo($pudoId)
    {
        $this->attributes['delivery'] = 'pudo';
        $this->attributes['pudo_id'] = (int) $pudoId;

        return $this;
    }

    /**
     * Deliver to a locker (Locker services).
     *
     * @return $this
     */
    public function deliverToLocker($lockerId)
    {
        $this->attributes['delivery'] = 'locker';
        $this->attributes['locker_id'] = (int) $lockerId;

        return $this;
    }

    /**
     * @return $this
     */
    public function address($first = null, $second = null, $zip = null)
    {
        if ($first !== null) {
            $this->attributes['address_first'] = $first;
        }
        if ($second !== null) {
            $this->attributes['address_second'] = $second;
        }
        if ($zip !== null) {
            $this->attributes['zip'] = $zip;
        }

        return $this;
    }

    /**
     * The shipping cost the partner charges (becomes the customs transport cost).
     *
     * @return $this
     */
    public function shippingCost($amount)
    {
        $this->attributes['shipping_cost'] = $amount;

        return $this;
    }

    /**
     * Request consolidation of the order's parcels. The service must enable it: if
     * the chosen service does not, the API rejects the order with `422`
     * ("Consolidation is not enabled for this service.").
     *
     * @return $this
     */
    public function consolidationRequired($required = true)
    {
        $this->attributes['consolidation_required'] = (bool) $required;

        return $this;
    }

    /**
     * @return $this
     */
    public function notes($notes)
    {
        $this->attributes['notes'] = $notes;

        return $this;
    }

    /**
     * Add a shipment. Accepts a {@see ShipmentBuilder} or a raw array.
     *
     * @param ShipmentBuilder|array<string, mixed> $shipment
     * @return $this
     */
    public function addShipment($shipment)
    {
        $this->shipments[] = $shipment;

        return $this;
    }

    /**
     * Set any additional top-level field directly (escape hatch).
     *
     * @param string $key
     * @return $this
     */
    public function set($key, $value)
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray()
    {
        $shipments = array();
        foreach ($this->shipments as $shipment) {
            $shipments[] = $shipment instanceof ShipmentBuilder ? $shipment->toArray() : $shipment;
        }

        $payload = $this->attributes;
        $payload['shipments'] = $shipments;

        return $payload;
    }
}
