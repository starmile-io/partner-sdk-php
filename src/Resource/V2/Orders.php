<?php

namespace Starmile\PartnerSdk\Resource\V2;

use Starmile\PartnerSdk\Resource\AbstractResource;

/**
 * Order intake and management on /api/v2 — the items/item vocabulary
 * (scopes: orders:create / orders:update / orders:cancel / labels:read).
 *
 * v2 renames the box array: v1's `parcels[]` is `items[]`, and your
 * per-box reference `item_id` is `item_id`. An item carries NO
 * Starmile tracking number of its own — you address it by your own
 * `item_id` (or its `merchant_tracking`), and the create response echoes
 * exactly those two fields per item. The order-level Starmile reference
 * is returned as `tracking_number`, and your own order reference is echoed
 * back as `order_id` (v1 overloaded `order_id` with our tracking number).
 */
final class Orders extends AbstractResource
{
    /**
     * Create an order. Pass the raw v2 payload (`service_id`, `order_id`,
     * `items[]`, ...). Idempotent on your `order_id`: re-sending one you
     * already used replays the original order with HTTP 200 and
     * `duplicate` = true instead of failing.
     *
     * @param array<string, mixed> $order
     * @return array{tracking_number: string, order_id: string, duplicate: bool, region_status: string, items: list<array{item_id: ?string, merchant_tracking: ?string}>}
     */
    public function create(array $order)
    {
        return $this->unwrap($this->connection->post('/api/v2/orders', $order));
    }

    /**
     * Download a single item's label PDF, addressed by its
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
     * Add a NEW item to an existing MULTI-item order (addressed by your own
     * `order_id`). One item per call, in the same shape as one entry of
     * {@see self::create()}'s `items[]`. The item carries no Starmile tracking of
     * its own, so the result is
     * `array('tracking_number' => ..., 'order_id' => ..., 'item' => array(
     * 'item_id' => ..., 'merchant_tracking' => ...))`. `products` is required.
     *
     * A SINGLE-item order carries its one box on the order itself and has no
     * separate items to add to — adding one is refused (409). Create a NEW order
     * for the additional package instead. A reused `item_id`, or a
     * `merchant_tracking` held by a real item, is a 422; a `merchant_tracking`
     * held by an unidentified package already at the hub is matched to this item.
     * Scope: `orders:update`.
     *
     * @param string               $orderId Your order reference (order_id).
     * @param array<string, mixed> $item    item_id, merchant_tracking, package_type,
     *                                      weight_grams, length_mm, width_mm,
     *                                      height_mm (all optional) and products[]
     *                                      (required).
     * @return array<string, mixed>
     */
    public function addItem($orderId, array $item)
    {
        $path = '/api/v2/orders/' . rawurlencode($orderId) . '/items';

        return $this->unwrap($this->connection->post($path, $item));
    }

    /**
     * Update a item while its order is still at the flow's first step
     * (partial; `products` replaces the full list). Addressed entirely by YOUR
     * references — your `order_id` and your `item_id`.
     *
     * @param string               $orderId    Your order reference.
     * @param string               $itemId Your item reference.
     * @param array<string, mixed> $changes    Any of: merchant_tracking, package_type,
     *                                         weight_grams, length_mm, width_mm,
     *                                         height_mm, products[].
     * @return array<string, mixed>
     */
    public function updateItem($orderId, $itemId, array $changes)
    {
        $path = '/api/v2/orders/' . rawurlencode($orderId) . '/items/' . rawurlencode($itemId);

        return $this->unwrap($this->connection->patch($path, $changes));
    }

    /**
     * Split one item off an existing multi-item order onto a NEW, cloned order —
     * the v2 twin of v1's {@see \Starmile\PartnerSdk\Resource\Orders::split()}. The
     * item is moved onto a fresh clone of the order; you name the new order with
     * `$newOrderId` (your own reference), which must be unused and different from
     * `$orderId`.
     *
     * The result follows the v2 wire:
     * `array('tracking_number' => ..., 'order_id' => ..., 'source_order_id' => ...,
     * 'item' => array('item_id' => ..., 'merchant_tracking' => ...))` — the NEW
     * order's Starmile reference is `tracking_number` and `order_id` echoes your
     * `$newOrderId`.
     *
     * An ordinary order is splittable only pre-custody; a CONSOLIDATION order can
     * be split any time before its boxes are packed (an item already received or
     * shelved can still be pulled out). A folded SINGLE-item order has no item to
     * split off, and an ALREADY-PACKED consolidation can no longer be split → 409;
     * a `$newOrderId` already used, or equal to `$orderId`, is a 422.
     * Scope: `orders:update`.
     *
     * @param string      $orderId    Your order reference to split FROM.
     * @param string      $itemId     Your item reference to peel off.
     * @param string      $newOrderId Your own reference for the new order.
     * @param string|null $reason     Optional free-text reason.
     * @return array<string, mixed>
     */
    public function split($orderId, $itemId, $newOrderId, $reason = null)
    {
        $path = '/api/v2/orders/' . rawurlencode($orderId) . '/items/' . rawurlencode($itemId) . '/split';
        $body = array('new_order_id' => $newOrderId);

        if ($reason !== null) {
            $body['reason'] = $reason;
        }

        return $this->unwrap($this->connection->post($path, $body));
    }

    /**
     * Cancel a single item while it is still at the flow's first step.
     * When it is the order's last active item, the order cancels with it.
     *
     * @param string      $orderId    Your order reference.
     * @param string      $itemId Your item reference.
     * @param string|null $reason     Optional free-text cancellation reason.
     * @return array<string, mixed>
     */
    public function cancelItem($orderId, $itemId, $reason = null)
    {
        $path = '/api/v2/orders/' . rawurlencode($orderId) . '/items/' . rawurlencode($itemId) . '/cancel';
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
