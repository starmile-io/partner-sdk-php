<?php

namespace Starmile\PartnerSdk\Resource;

use Starmile\PartnerSdk\Builder\OrderBuilder;

/**
 * Order intake and pre-custody management.
 *
 *   POST  /api/v1/orders                                  (scope: orders:create)
 *   PATCH /api/v1/orders/{order}/parcels/{parcel}     (scope: orders:update)
 *   POST  /api/v1/orders/{order}/cancel                   (scope: orders:cancel)
 *
 * Orders and parcels are addressed by the partner's OWN references: `{order}` is
 * the `order_id` you sent on create, `{parcel}` is the `item_id` of a parcel.
 */
final class Orders extends AbstractResource
{
    /**
     * Create an order. Accepts either an {@see OrderBuilder} or a raw payload array
     * matching the API body (`service_id`, `order_id`, `parcels[]`, ...). Returns
     * the created order.
     *
     * @param OrderBuilder|array<string, mixed> $order
     * @return array<string, mixed>
     */
    public function create($order)
    {
        $payload = $order instanceof OrderBuilder ? $order->toArray() : $order;

        return $this->unwrap($this->connection->post('/api/v1/orders', $payload));
    }

    /**
     * Update a not-yet-received parcel (partial). Sending `products` REPLACES the
     * parcel's full product list. 409 once the parcel has been received.
     *
     * @param string                $orderId    The partner's order reference (order_id).
     * @param string                $itemId     The partner's parcel reference (item_id).
     * @param array<string, mixed>  $changes    Any of: merchant_tracking, package_type,
     *                                           weight_grams, length_mm, width_mm,
     *                                           height_mm, products[].
     * @return array<string, mixed>
     */
    public function updateParcel($orderId, $itemId, array $changes)
    {
        $path = '/api/v1/orders/' . rawurlencode($orderId) . '/parcels/' . rawurlencode($itemId);

        return $this->unwrap($this->connection->patch($path, $changes));
    }

    /**
     * Cancel an order while it is still in a pre-custody waiting status. 409 once
     * any package is in our custody.
     *
     * @param string      $orderId The partner's order reference (order_id).
     * @param string|null $reason  Optional free-text cancellation reason.
     * @return array<string, mixed>
     */
    public function cancel($orderId, $reason = null)
    {
        $path = '/api/v1/orders/' . rawurlencode($orderId) . '/cancel';
        $body = $reason === null ? array() : array('reason' => $reason);

        return $this->unwrap($this->connection->post($path, $body));
    }

    /**
     * Single-resource endpoints wrap the entity under a `data` key.
     *
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function unwrap(array $response)
    {
        return isset($response['data']) && is_array($response['data']) ? $response['data'] : $response;
    }
}
