<?php

namespace Starmile\PartnerSdk\Builder;

/**
 * Fluent builder for an order intake payload (POST /api/v1/orders). Required:
 * a `service_id` (one of GET /api/v1/services), an `order_id` (the partner's own
 * order reference), and at least one parcel. The corridor (origin/destination) and
 * delivery type come from the Service; there is no partner-supplied rate — Finance
 * resolves the billing rate internally at the invoice-trigger status.
 */
final class OrderBuilder
{
    /** @var array<string, mixed> */
    private $attributes = array();

    /** @var array<int, ParcelBuilder|array<string, mixed>> */
    private $parcels = array();

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
     * The recipient. `govId` is the recipient's government ID (`gov_id`) — the AZ FIN
     * or a foreign passport number — used for the customs declaration.
     *
     * At least one of `phone` / `email` is REQUIRED by the API: the customer must be
     * reachable (delivery coordination + any government-ID request). Omitting both is
     * rejected `422`.
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
     * Deliver to a region (Home Delivery services). You address the destination by
     * YOUR OWN reference — your parent region id/code plus your leaf region id/code
     * — which Starmile maps, per partner, to one of its regions. Both are required;
     * the parent disambiguates a leaf that repeats across parents. Have an operator
     * map your regions in Starmile first (an unmapped reference is rejected).
     * Optionally set the address lines.
     *
     * @param  string|int  $parentRegion  your parent region id/code (e.g. "1")
     * @param  string|int  $region        your leaf region id/code (e.g. "2")
     * @return $this
     */
    public function deliverHome($parentRegion, $region, $addressFirst = null, $addressSecond = null, $zip = null)
    {
        $this->attributes['parent_region'] = (string) $parentRegion;
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
     * Add a parcel. Accepts a {@see ParcelBuilder} or a raw array.
     *
     * @param ParcelBuilder|array<string, mixed> $parcel
     * @return $this
     */
    public function addParcel($parcel)
    {
        $this->parcels[] = $parcel;

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
        $parcels = array();
        foreach ($this->parcels as $parcel) {
            $parcels[] = $parcel instanceof ParcelBuilder ? $parcel->toArray() : $parcel;
        }

        $payload = $this->attributes;
        $payload['parcels'] = $parcels;

        return $payload;
    }
}
