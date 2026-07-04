<?php

namespace Starmile\PartnerSdk\Enum;

/**
 * The region resolution status returned as `region_status` on the create-order
 * response (POST /api/v1/orders). For a Home Delivery service the destination
 * region is resolved from your own (parent, leaf) reference, map-only per partner:
 *
 *   - PENDING_MAPPING  The order was accepted, but your region reference is not
 *                      mapped yet. It is queued for an operator to map in Starmile;
 *                      once mapped, the waiting order is resolved automatically —
 *                      you do NOT need to resend it.
 *   - MAPPED           The region was resolved.
 *   - NOT_APPLICABLE   The service has no home region (PUDO / locker / clearance).
 *
 * Values mirror the server vocabulary VERBATIM. Provided as constants so
 * integrations can compare against names instead of magic strings.
 */
final class RegionStatus
{
    const MAPPED = 'mapped';
    const PENDING_MAPPING = 'pending_mapping';
    const NOT_APPLICABLE = 'not_applicable';
}
