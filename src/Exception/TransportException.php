<?php

namespace Starmile\PartnerSdk\Exception;

/**
 * Thrown when the HTTP request never reached a usable response: a network error,
 * DNS failure, connection timeout, or a TLS problem. No status code is available.
 */
class TransportException extends StarmileException
{
}
