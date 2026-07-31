<?php

namespace Starmile\PartnerSdk\Resource;

use Starmile\PartnerSdk\Builder\OrderBuilder;

/**
 * Order intake and pre-custody management.
 *
 *   POST  /api/v1/orders                                  (scope: orders:create)
 *   GET   /api/v1/orders/label                             (scope: labels:read)
 *   PATCH /api/v1/orders/{order}/parcels/{parcel}     (scope: orders:update)
 *   POST  /api/v1/orders/{order}/parcels/{parcel}/cancel  (scope: orders:cancel)
 *   POST  /api/v1/orders/{order}/cancel                   (scope: orders:cancel)
 *
 * Orders and parcels are addressed by the partner's OWN references: `{order}` is
 * the `order_id` you sent on create, `{parcel}` is the `item_id` of a parcel.
 */
final class Orders extends AbstractResource
{
    /**
     * Create an order. Accepts either an {@see OrderBuilder} or a raw payload array
     * matching the API body (`service_id`, `order_id`, `parcels[]`, ...). Returns the
     * created order's `order_id` (its Starmile tracking number), a `duplicate` flag,
     * a `region_status` ({@see \Starmile\PartnerSdk\Enum\RegionStatus}), and `items` —
     * one entry per parcel mapping your `item_id` (as sent, or null) to `parcel_id`
     * (our parcel's Starmile tracking number):
     * `['order_id' => 'STM…', 'duplicate' => false, 'region_status' => 'mapped', 'items' => [['item_id' => 'PKG-1', 'parcel_id' => 'STM…']]]`.
     *
     * For a Home Delivery service the destination region is resolved from your own
     * (parent, leaf) reference, map-only per partner. If it is not mapped yet the
     * order is still ACCEPTED with `region_status` `pending_mapping`: an operator maps
     * it in Starmile and the waiting order is resolved automatically — you do not
     * resend it. `mapped` = resolved; `not_applicable` = no home region (PUDO /
     * locker / clearance).
     *
     * Your own references must be unique — each parcel's `item_id` and each
     * `merchant_tracking`. Reusing one that already exists under a different order
     * (or repeating one across parcels in the same order) is rejected `422`.
     *
     * Re-sending an `order_id` you already used is SAFE — the call is idempotent on
     * it. Nothing is created: the original order is replayed with HTTP `200` (not
     * `201`) and `duplicate` = true, carrying the same `order_id`, `region_status`
     * and `items` you got the first time. Use it to recover our ids after a timeout;
     * the rest of the body is ignored on a replay, so use {@see self::updateParcel()}
     * to change an accepted order.
     *
     * @param OrderBuilder|array<string, mixed> $order
     * @return array{order_id: string, duplicate: bool, region_status: string, items: list<array{item_id: ?string, parcel_id: string}>}
     */
    public function create($order)
    {
        $payload = $order instanceof OrderBuilder ? $order->toArray() : $order;

        return $this->unwrap($this->connection->post('/api/v1/orders', $payload));
    }

    /**
     * Download a SINGLE parcel's printable label as a PDF, rendered from the org's
     * default parcel label template. Addressed by the parcel's `merchant_tracking`
     * (the barcode you sent on create). Returns the raw PDF bytes — write them to a
     * file or stream them to the client. Scope: `labels:read`.
     *
     * To address a parcel by our parcel id instead, use
     * {@see self::labelByParcelId()}; to print a whole order's own label, use
     * {@see self::labelByOrderId()}.
     *
     * @param string $merchantTracking The parcel's merchant tracking (sticker code).
     * @return string the raw PDF bytes
     */
    public function label($merchantTracking)
    {
        return $this->connection->getRaw(
            '/api/v1/orders/label',
            array('merchant_tracking' => $merchantTracking),
            'application/pdf'
        );
    }

    /**
     * Download a SINGLE parcel's label PDF, addressed by its `parcel_id` — our parcel
     * id, the value returned as `items[].parcel_id` when you create the order. Scope:
     * `labels:read`.
     *
     * @param string $parcelId Our parcel id (e.g. STM0000000121).
     * @return string the raw PDF bytes
     */
    public function labelByParcelId($parcelId)
    {
        return $this->connection->getRaw(
            '/api/v1/orders/label',
            array('parcel_id' => $parcelId),
            'application/pdf'
        );
    }

    /**
     * Download a whole ORDER's label as a PDF — a single order label whose barcode,
     * weight, dimensions and contents are all the order's (contents aggregated across
     * its parcels), not any single parcel's. Addressed by the order's `order_id` (the
     * order's tracking number, returned as `order_id` when you create the order).
     * Scope: `labels:read`.
     *
     * @param string $orderId The order's tracking number (e.g. STM0000000120).
     * @return string the raw PDF bytes
     */
    public function labelByOrderId($orderId)
    {
        return $this->connection->getRaw(
            '/api/v1/orders/label',
            array('order_id' => $orderId),
            'application/pdf'
        );
    }

    /**
     * Update a parcel while its order is still at the flow's FIRST step (partial).
     * Sending `products` REPLACES the parcel's full product list. 409 once the order
     * has moved past its first step (e.g. the parcel has been received).
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
     * Cancel a SINGLE parcel (one package of an order) while it is still at the
     * flow's FIRST step. When it is the order's last active parcel, the order is
     * cancelled too. 409 once the parcel has been received / moved past the first
     * step. Scope: `orders:cancel`.
     *
     * @param string      $orderId The partner's order reference (order_id).
     * @param string      $itemId  The partner's parcel reference (item_id).
     * @param string|null $reason  Optional free-text cancellation reason.
     * @return array<string, mixed>
     */
    public function cancelParcel($orderId, $itemId, $reason = null)
    {
        $path = '/api/v1/orders/' . rawurlencode($orderId) . '/parcels/' . rawurlencode($itemId) . '/cancel';
        $body = $reason === null ? array() : array('reason' => $reason);

        return $this->unwrap($this->connection->post($path, $body));
    }

    /**
     * Cancel an order while it is still at its flow's FIRST step. 409 once any
     * package has moved past the first step (e.g. been received or handed to a carrier).
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
