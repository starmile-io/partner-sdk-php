<?php

namespace Starmile\PartnerSdk\Exception;

/**
 * HTTP 401 — the bearer token is missing, expired, or invalid, or the client
 * credentials were rejected at the token endpoint.
 */
class AuthenticationException extends ApiException
{
}
