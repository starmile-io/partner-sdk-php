<?php

namespace Starmile\PartnerSdk\Exception;

/**
 * HTTP 409 — the resource can no longer change in the requested way: a shipment
 * already received, or an order already in custody (no longer cancellable).
 */
class ConflictException extends ApiException
{
}
