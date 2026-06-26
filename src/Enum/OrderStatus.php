<?php

namespace Starmile\PartnerSdk\Enum;

/**
 * The status values a package may carry in the status pool (the `status` /
 * `previous_status` fields of GET /api/v1/partner/changes). Provided as constants
 * so integrations can compare against names instead of magic strings.
 *
 * Values mirror the server vocabulary VERBATIM — including the intentional casing
 * of `Consolidated` and the hyphen in `dispatched-from_hub`. Do not "correct" them.
 */
final class OrderStatus
{
    const WAITING_FOR_ARRIVAL = 'waiting_for_arrival';
    const WAITING_FOR_PICKUP = 'waiting_for_pickup';
    const WAITING_FOR_DROP_OFF = 'waiting_for_drop_off';
    const PICKUP_FAILED = 'pickup_failed';
    const DROPOFF_CANCELLED = 'dropoff_cancelled';
    const CANCELLED = 'cancelled';
    const RETURNED = 'returned';
    const ORDER_CREATED = 'order_created';
    const PAYMENT_CONFIRMED = 'payment_confirmed';
    const ORDER_CANCELLED = 'order_cancelled';
    const RECEIVED_AT_HUB = 'received_at_hub';
    const MATCHED = 'matched';
    const CUSTOMER_INFO_REQUESTED = 'customer_info_requested';
    const CUSTOMER_INFO_CONFIRMED = 'customer_info_confirmed';
    const CONSOLIDATION_RACKED = 'consolidation_racked';
    const CONSOLIDATION_STUFFED = 'consolidation_stuffed';
    const CONSOLIDATION_READY = 'consolidation_ready';
    const CONSOLIDATION_PICKED = 'consolidation_picked';
    const CONSOLIDATED = 'Consolidated';
    const ACCEPTED_AT_HUB = 'accepted_at_hub';
    const DECLARATION_PUSHED = 'declaration_pushed';
    const DECLARATION_CONFIRMED = 'declaration_confirmed';
    const SORTED = 'sorted';
    const BATCH_CREATED = 'batch_created';
    const BAG_CREATED = 'bag_created';
    const BAG_STUFFED = 'bag_stuffed';
    const BAG_CLOSED = 'bag_closed';
    const BATCH_CLOSED = 'batch_closed';
    const SHIPMENT_CREATED = 'shipment_created';
    const SHIPMENT_STUFFED = 'shipment_stuffed';
    const READY_FOR_DISPATCH = 'ready_for_dispatch';
    const PARCEL_RACKED_AT_HUB = 'parcel_racked_at_hub';
    const PARCEL_STUFFED_AT_HUB = 'parcel_stuffed_at_hub';
    const DISPATCHED_FROM_HUB = 'dispatched-from_hub';
    const LOADING_IN_PROGRESS = 'loading_in_progress';
    const IN_TRANSIT = 'in_transit';
    const DISCHARGING = 'discharging';
    const PICKED_UP = 'picked_up';
    const OUT_FOR_DELIVERY = 'out_for_delivery';
    const DELIVERED = 'delivered';
    const DELIVERY_FAILED = 'delivery_failed';
    const FORWARDED_TO_DESTINATION = 'forwarded_to_destination';
    const HANDED_OVER_TO_PUDO = 'handed_over_to_pudo';
    const ARRIVED_AT_TERMINAL = 'arrived_at_terminal';
    const CUSTOMS_IN_PROGRESS = 'customs_in_progress';
    const CUSTOMS_COMPLETED = 'customs_completed';
    const TRANSPORT_DOCUMENT_ENTERED = 'transport_document_entered';
    const CUSTOMS_RELEASED = 'customs_released';
    const DEPARTED = 'departed';
    const CUSTOMS_HOLD = 'customs_hold';
    const CUSTOMS_HOLD_RESOLVED = 'customs_hold_resolved';
    const RECEIVED_AT_PUDO = 'received_at_pudo';
    const DISPATCHED_FROM_PUDO = 'dispatched_from_pudo';
    const ACCEPTED_AT_PUDO = 'accepted_at_pudo';
    const PARCEL_RACKED_AT_PUDO = 'parcel_racked_at_pudo';
    const PARCEL_STUFFED_AT_PUDO = 'parcel_stuffed_at_pudo';
    const UNDELIVERED = 'undelivered';
}
