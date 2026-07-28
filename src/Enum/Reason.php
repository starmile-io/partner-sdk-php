<?php

namespace Starmile\PartnerSdk\Enum;

/**
 * The reason codes explaining WHY a status change happened — the `reason` field
 * of a status pool row (GET /api/v1/partner/changes), and an accepted value for
 * the `reason` data field of an event you report.
 *
 * A status says what happened (`delivery_failed`); a reason says why
 * (`customer_absent`). Provided as constants so you compare against names rather
 * than magic strings.
 *
 * Two things to code defensively against, both by design:
 *
 *  - `reason` is often NULL. Most changes have no why — a parcel arriving at a
 *    hub simply arrived — so never assume the field is populated.
 *  - the list GROWS. Codes are permanent (never renamed, never reused, so a
 *    comparison you write today keeps working), but new ones are added as the
 *    platform models more situations. Treat an unrecognised code as "some other
 *    reason" and fall back to `reason_detail`, rather than failing.
 *
 * `reason_detail`, alongside it, is free text a person wrote. Show it to a human;
 * never branch on it.
 */
final class Reason
{
    // --- Customs holds (status: customs_hold) --------------------------------

    /** The parcel has no customs declaration. */
    const MISSING_DECLARATION = 'missing_declaration';

    /** The declared information does not match the parcel. */
    const INACCURATE_INFORMATION = 'inaccurate_information';

    /** The contents are prohibited or restricted for import. */
    const PROHIBITED_CONTENT_RESTRICTED_ITEM = 'prohibited_content_restricted_item';

    /** The shipment exceeds the recipient's personal allowance — treated as commercial. */
    const COMMERCIAL_QUANTITY_PERSONAL_ALLOWANCE_EXCEEDED = 'commercial_quantity_personal_allowance_exceeded';

    // --- Failed deliveries (status: delivery_failed) -------------------------

    /** Nobody was there to receive the parcel. */
    const CUSTOMER_ABSENT = 'customer_absent';

    /** The courier could not find the address. */
    const ADDRESS_NOT_FOUND = 'address_not_found';

    /** The recipient refused the parcel. */
    const CUSTOMER_REFUSED = 'customer_refused';

    /** The courier could not contact the recipient. */
    const COULD_NOT_REACH_CUSTOMER = 'could_not_reach_customer';

    /** The address is wrong or missing details needed to deliver. */
    const WRONG_OR_INCOMPLETE_ADDRESS = 'wrong_or_incomplete_address';

    // --- Cancellations (status: cancelled) -----------------------------------

    /** You cancelled the order or parcel through the API. */
    const CANCELLED_BY_PARTNER = 'cancelled_by_partner';

    /** The recipient cancelled it themselves. */
    const CANCELLED_BY_CUSTOMER = 'cancelled_by_customer';

    /** Cancelled on the recipient's behalf by an operator. */
    const CANCELLED_BY_OPERATOR = 'cancelled_by_operator';
}
