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
            array('tracking_number' => $trackingNumber),
            'application/pdf'
        );
    }

    /**
     * Download a whole ORDER's label PDF, addressed by YOUR OWN order reference
     * (the `order_id` you sent on create). Scope: `labels:read`.
     *
     * On v2 `order_id` means YOUR reference EVERYWHERE — on create, on the status
     * pool, on delivery-code and here. v1 overloaded it with Starmile's tracking
     * number; v1 keeps that meaning and is unaffected.
     *
     * @param string $orderId
     * @return string the raw PDF bytes
     */
    public function labelByOrderId($orderId)
    {
        return $this->connection->getRaw(
            '/api/v2/orders/label',
            array('order_id' => $orderId),
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
     * Split one or more items off an existing multi-item order onto a NEW, cloned
     * order — the v2 twin of v1's {@see \Starmile\PartnerSdk\Resource\Orders::split()}.
     * The items you name in `$itemIds` are moved onto ONE fresh clone of the order
     * (the source keeps the rest); you name the new order with `$newOrderId` (your
     * own reference), which must be unused and different from `$orderId`.
     *
     * The result follows the v2 wire:
     * `array('tracking_number' => ..., 'order_id' => ..., 'source_order_id' => ...,
     * 'items' => array(array('item_id' => ..., 'merchant_tracking' => ...), ...))` —
     * the NEW order's Starmile reference is `tracking_number` and `order_id` echoes
     * your `$newOrderId`. Move a SINGLE item and the new order is a single-item
     * order, stored the v2 way (the item lives on the order itself, no separate item
     * to address), so — as with any v2 single-item order — you cannot
     * {@see self::addItem()} to it. Move TWO OR MORE and the new order is a genuine
     * multi-item order that keeps its items.
     *
     * An ordinary order is splittable only pre-custody; a CONSOLIDATION order can
     * be split any time before its boxes are packed (an item already received or
     * shelved can still be pulled out). A folded SINGLE-item source order has no
     * item to split off, an ALREADY-PACKED consolidation can no longer be split, and
     * naming every item (nothing left) → 409; an `item_id` that names no active item
     * → 404; a `$newOrderId` already used, or equal to `$orderId`, is a 422.
     * Scope: `orders:update`.
     *
     * @param string      $orderId    Your order reference to split FROM.
     * @param string[]    $itemIds    Your item references to peel off — one or more, distinct.
     * @param string      $newOrderId Your own reference for the new order.
     * @param string|null $reason     Optional free-text reason.
     * @return array<string, mixed>
     */
    public function split($orderId, array $itemIds, $newOrderId, $reason = null)
    {
        $path = '/api/v2/orders/' . rawurlencode($orderId) . '/split';
        $body = array('item_ids' => array_values($itemIds), 'new_order_id' => $newOrderId);

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
     * The delivery code for one of your orders — the code the recipient reads to
     * the courier at the door. Addressed by YOUR reference (`order_id`) or by the
     * Starmile `tracking_number`; pass exactly one.
     *
     * ORDER-LEVEL: one order carries one code however many boxes it ships in, and
     * the code does not change after a failed attempt.
     *
     * Available as soon as the order exists, so you can show it to your customer
     * without polling for it.
     *
     * `status` says what the code is worth, and IS NOT AN ERROR — read it before
     * displaying anything:
     *
     *  - `active`         — show it;
     *  - `used`           — the parcel is delivered and the code has done its job;
     *  - `not_required`   — this organization does not use delivery codes, so
     *                       `delivery_code` is null and nothing should be shown;
     *  - `not_yet_issued` — no code on the order (only orders created before
     *                       codes existed).
     *
     * An order that is not yours is a 404, never a 403. Scope:
     * `delivery_code:read` (NOT `pod:read` — a partner may hold the
     * proof-of-delivery grant without this one).
     *
     * @param string      $orderId        Your order reference, or null when addressing by tracking number.
     * @param string|null $trackingNumber The Starmile tracking number instead.
     * @return array{tracking_number: string, order_id: ?string, delivery_code: ?string, status: string}
     */
    public function deliveryCode($orderId, $trackingNumber = null)
    {
        $query = $trackingNumber === null
            ? array('order_id' => $orderId)
            : array('tracking_number' => $trackingNumber);

        return $this->unwrap($this->connection->get('/api/v2/orders/delivery-code', $query));
    }

    /**
     * The delivery code addressed by the Starmile `tracking_number` rather than
     * your own reference. {@see self::deliveryCode()} for the `status` values.
     *
     * @param string $trackingNumber
     * @return array{tracking_number: string, order_id: ?string, delivery_code: ?string, status: string}
     */
    public function deliveryCodeByTrackingNumber($trackingNumber)
    {
        return $this->deliveryCode(null, $trackingNumber);
    }

    /**
     * The PROOF OF DELIVERY for a delivered order, as PDF bytes.
     *
     * One section per handover: a courier records one per BOX, while a pickup at
     * a PUDO point is ONE collection for the whole order (it is a single act,
     * verified once), so a multi-box order may show either shape.
     *
     * Each section prints your own references, the recipient, the address, when it
     * happened IN THE DELIVERY COUNTRY'S OWN TIMEZONE, and then only the evidence
     * that was actually captured — the delivery code in full, the signature and
     * the photo. Nothing is printed empty: a proof that was never captured does
     * not appear at all, and a handover with none says so in a sentence.
     *
     * Pass `$merchantTracking` to narrow the document to ONE box.
     *
     * STATUS CODES ARE NOT INTERCHANGEABLE HERE:
     *  - 409 — the order (or the named box) is not delivered yet. It exists and
     *          it is yours; do not go looking for a reference problem.
     *  - 404 — the order is not yours, or we do not hold it. Deliberately the
     *          same answer for both.
     *
     * A PARTLY delivered order still returns its document, with the remaining
     * boxes listed under "Not yet delivered". Scope: `pod:read` (NOT
     * `delivery_code:read` — a partner may hold either without the other).
     *
     * @param string      $orderId          Your order reference, or null when addressing by tracking number.
     * @param string|null $trackingNumber   The Starmile tracking number instead.
     * @param string|null $merchantTracking Optional — narrow to one box.
     * @return string the raw PDF bytes
     */
    public function proofOfDelivery($orderId, $trackingNumber = null, $merchantTracking = null)
    {
        $query = $trackingNumber === null
            ? array('order_id' => $orderId)
            : array('tracking_number' => $trackingNumber);

        if ($merchantTracking !== null) {
            $query['merchant_tracking'] = $merchantTracking;
        }

        return $this->connection->getRaw('/api/v2/orders/pod', $query, 'application/pdf');
    }

    /**
     * The proof of delivery addressed by the Starmile `tracking_number`.
     * {@see self::proofOfDelivery()} for everything else.
     *
     * @param string      $trackingNumber
     * @param string|null $merchantTracking
     * @return string the raw PDF bytes
     */
    public function proofOfDeliveryByTrackingNumber($trackingNumber, $merchantTracking = null)
    {
        return $this->proofOfDelivery(null, $trackingNumber, $merchantTracking);
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
