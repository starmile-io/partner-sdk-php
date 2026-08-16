<?php

namespace Starmile\PartnerSdk\Resource\V2;

use Starmile\PartnerSdk\Resource\AbstractResource;

/**
 * Order intake and management on /api/v2 — the sub_orders vocabulary
 * (scopes: orders:create / orders:update / orders:cancel / labels:read).
 *
 * v2 renames the box array: v1's `parcels[]` is `sub_orders[]`, and your
 * per-box reference `item_id` is `sub_order_id`. A sub-order carries NO
 * Starmile tracking number of its own — you address it by your own
 * `sub_order_id` (or its `merchant_tracking`), and the create response echoes
 * exactly those two fields per sub-order. The order-level Starmile reference
 * is returned as `tracking_number`, and your own order reference is echoed
 * back as `order_id` (v1 overloaded `order_id` with our tracking number).
 */
final class Orders extends AbstractResource
{
    /**
     * Create an order. Pass the raw v2 payload (`service_id`, `order_id`,
     * `sub_orders[]`, ...). Idempotent on your `order_id`: re-sending one you
     * already used replays the original order with HTTP 200 and
     * `duplicate` = true instead of failing.
     *
     * @param array<string, mixed> $order
     * @return array{tracking_number: string, order_id: string, duplicate: bool, region_status: string, sub_orders: list<array{sub_order_id: ?string, merchant_tracking: ?string}>}
     */
    public function create(array $order)
    {
        return $this->unwrap($this->connection->post('/api/v2/orders', $order));
    }

    /**
     * Download a single sub-order's label PDF, addressed by its
     * `merchant_tracking` (the sticker code you sent). Scope: `labels:read`.
     *
     * @param string $merchantTracking
     * @return string the raw PDF bytes
     */
    public function label($merchantTracking)
    {
        return $this->connection->getRaw(
            '/api/v2/orders/label',
            array('merchant_tracking' => $merchantTracking),
            'application/pdf'
        );
    }

    /**
     * Download a whole ORDER's label PDF, addressed by the order's Starmile
     * `tracking_number` (returned on create). Scope: `labels:read`.
     *
     * @param string $trackingNumber
     * @return string the raw PDF bytes
     */
    public function labelByTrackingNumber($trackingNumber)
    {
        return $this->connection->getRaw(
            '/api/v2/orders/label',
            array('order_id' => $trackingNumber),
            'application/pdf'
        );
    }

    /**
     * Update a sub-order while its order is still at the flow's first step
     * (partial; `products` replaces the full list). Addressed entirely by YOUR
     * references — your `order_id` and your `sub_order_id`.
     *
     * @param string               $orderId    Your order reference.
     * @param string               $subOrderId Your sub-order reference.
     * @param array<string, mixed> $changes    Any of: merchant_tracking, package_type,
     *                                         weight_grams, length_mm, width_mm,
     *                                         height_mm, products[].
     * @return array<string, mixed>
     */
    public function updateSubOrder($orderId, $subOrderId, array $changes)
    {
        $path = '/api/v2/orders/' . rawurlencode($orderId) . '/sub-orders/' . rawurlencode($subOrderId);

        return $this->unwrap($this->connection->patch($path, $changes));
    }

    /**
     * Cancel a single sub-order while it is still at the flow's first step.
     * When it is the order's last active sub-order, the order cancels with it.
     *
     * @param string      $orderId    Your order reference.
     * @param string      $subOrderId Your sub-order reference.
     * @param string|null $reason     Optional free-text cancellation reason.
     * @return array<string, mixed>
     */
    public function cancelSubOrder($orderId, $subOrderId, $reason = null)
    {
        $path = '/api/v2/orders/' . rawurlencode($orderId) . '/sub-orders/' . rawurlencode($subOrderId) . '/cancel';
        $body = $reason === null ? array() : array('reason' => $reason);

        return $this->unwrap($this->connection->post($path, $body));
    }

    /**
     * Cancel an order while it is still at its flow's first step.
     *
     * @param string      $orderId Your order reference.
     * @param string|null $reason  Optional free-text cancellation reason.
     * @return array<string, mixed>
     */
    public function cancel($orderId, $reason = null)
    {
        $path = '/api/v2/orders/' . rawurlencode($orderId) . '/cancel';
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
