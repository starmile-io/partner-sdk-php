<?php

namespace Starmile\PartnerSdk\Enum;

/**
 * The inbound lifecycle events a partner may report on POST /api/v1/partner/events.
 * Each constant is a server-recognised event type. The map below records, for each
 * type, the OAuth2 scope that grants it and the exact set of optional `data` fields
 * it accepts — mirroring the server contract so the SDK can reject unknown fields
 * before a request is sent. Every event additionally accepts a free-text `note`.
 *
 * Genuinely ambiguous events (e.g. customs.cleared across export/import,
 * *.exception) are intentionally NOT modelled by the server and are absent here.
 */
final class EventType
{
    // events:transport — carrier
    const SHIPMENT_ACCEPTED_BY_CARRIER = 'shipment.accepted_by_carrier';
    const SHIPMENT_ARRIVED_AT_TERMINAL = 'shipment.arrived_at_terminal';
    const SHIPMENT_OUT_FOR_DELIVERY = 'shipment.out_for_delivery';
    const SHIPMENT_DELIVERED = 'shipment.delivered';
    const SHIPMENT_DELIVERY_FAILED = 'shipment.delivery_failed';
    const SHIPMENT_RETURNED = 'shipment.returned';

    // events:pudo — pick-up / drop-off point
    const PARCEL_ACCEPTED = 'parcel.accepted';
    const PARCEL_RACKED_AT_HUB = 'parcel.parcel_racked_at_hub';
    const PARCEL_RECEIVED = 'parcel.received';
    const PARCEL_DELIVERED = 'parcel.delivered';
    const PARCEL_TIMEOUT = 'parcel.timeout';
    const PARCEL_DROPOFF_CANCELLED = 'parcel.dropoff_cancelled';

    // events:customs — customs broker
    const CUSTOMS_HELD = 'customs.held';

    // leg:handoff — foreign organization on the next leg
    const LEG_RECEIVED = 'leg.received';
    const LEG_OUT_FOR_DELIVERY = 'leg.out_for_delivery';
    const LEG_DELIVERED = 'leg.delivered';
    const LEG_DELIVERY_FAILED = 'leg.delivery_failed';
    const LEG_RETURNED = 'leg.returned';

    /**
     * The optional `data` fields each event type accepts, beyond the universal
     * `note`. The empty array means the event takes no fields other than `note`.
     *
     * @var array<string, array<int, string>>
     */
    private static $dataFields = array(
        self::SHIPMENT_ACCEPTED_BY_CARRIER => array('carrier_reference', 'awb'),
        self::SHIPMENT_ARRIVED_AT_TERMINAL => array('terminal'),
        self::SHIPMENT_OUT_FOR_DELIVERY => array('driver', 'eta'),
        self::SHIPMENT_DELIVERED => array('recipient_name', 'signed_by', 'proof_of_delivery'),
        self::SHIPMENT_DELIVERY_FAILED => array('reason', 'attempt'),
        self::SHIPMENT_RETURNED => array('reason'),
        self::PARCEL_ACCEPTED => array('point_code', 'shelf'),
        self::PARCEL_RACKED_AT_HUB => array('point_code'),
        self::PARCEL_RECEIVED => array('point_code'),
        self::PARCEL_DELIVERED => array('recipient_name', 'signed_by', 'proof_of_delivery'),
        self::PARCEL_TIMEOUT => array(),
        self::PARCEL_DROPOFF_CANCELLED => array('reason'),
        self::CUSTOMS_HELD => array('reason', 'reference'),
        self::LEG_RECEIVED => array('reference'),
        self::LEG_OUT_FOR_DELIVERY => array('driver', 'eta'),
        self::LEG_DELIVERED => array('recipient_name', 'signed_by', 'proof_of_delivery'),
        self::LEG_DELIVERY_FAILED => array('reason', 'attempt'),
        self::LEG_RETURNED => array('reason'),
    );

    /**
     * Every recognised event type.
     *
     * @return array<int, string>
     */
    public static function all()
    {
        return array_keys(self::$dataFields);
    }

    /**
     * @return bool
     */
    public static function isValid($type)
    {
        return isset(self::$dataFields[$type]);
    }

    /**
     * The scope a credential must hold to report the given event type, derived
     * from the event-name family (the same rule the server applies).
     *
     * @return string
     */
    public static function scopeFor($type)
    {
        $family = strpos($type, '.') !== false ? substr($type, 0, strpos($type, '.')) : $type;

        switch ($family) {
            case 'shipment':
                return Scope::EVENTS_TRANSPORT;
            case 'parcel':
                return Scope::EVENTS_PUDO;
            case 'customs':
                return Scope::EVENTS_CUSTOMS;
            case 'leg':
                return Scope::LEG_HANDOFF;
            default:
                return '';
        }
    }

    /**
     * The full set of `data` fields a type accepts, including the universal `note`.
     *
     * @return array<int, string>
     */
    public static function dataFieldsFor($type)
    {
        if (!isset(self::$dataFields[$type])) {
            return array('note');
        }

        return array_merge(array('note'), self::$dataFields[$type]);
    }
}
