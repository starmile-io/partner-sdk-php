<?php

namespace Starmile\PartnerSdk\Enum;

/**
 * The OAuth2 scopes (abilities) a Partner API credential may hold. Access is
 * granted as a set of scopes on the credential and enforced per endpoint (a
 * missing scope on a route → 403) and per inbound event type (an event outside
 * the granted scope → 422).
 *
 * Constants mirror the server contract one-for-one; do not invent new values.
 */
final class Scope
{
    /** Create orders — POST /api/v1/orders. */
    const ORDERS_CREATE = 'orders:create';

    /** Update a parcel before it is received — PATCH /api/v1/orders/{order}/parcels/{parcel}. */
    const ORDERS_UPDATE = 'orders:update';

    /** Cancel an order before it is in custody — POST /api/v1/orders/{order}/cancel. */
    const ORDERS_CANCEL = 'orders:cancel';

    /** Read the catalogue — GET /api/v1/services and GET /api/v1/rates. */
    const CATALOGUE_READ = 'catalogue:read';

    /** Poll the status pool — GET /api/v1/partner/changes. */
    const STATUS_READ = 'status:read';

    /** Report transport (carrier) events — shipment.*. */
    const EVENTS_TRANSPORT = 'events:transport';

    /** Report PUDO point events — parcel.*. */
    const EVENTS_PUDO = 'events:pudo';

    /** Report customs-broker events — customs.*. */
    const EVENTS_CUSTOMS = 'events:customs';

    /** Report foreign-organization leg events — leg.*. */
    const LEG_HANDOFF = 'leg:handoff';

    /**
     * Every scope value.
     *
     * @return array<int, string>
     */
    public static function all()
    {
        return array(
            self::ORDERS_CREATE,
            self::ORDERS_UPDATE,
            self::ORDERS_CANCEL,
            self::CATALOGUE_READ,
            self::STATUS_READ,
            self::EVENTS_TRANSPORT,
            self::EVENTS_PUDO,
            self::EVENTS_CUSTOMS,
            self::LEG_HANDOFF,
        );
    }
}
