<?php

namespace Starmile\PartnerSdk\Exception;

/**
 * HTTP 404 — the referenced resource does not exist, or belongs to another
 * partner (a foreign order/parcel reference is indistinguishable from a missing
 * one, by design).
 */
class NotFoundException extends ApiException
{
}
