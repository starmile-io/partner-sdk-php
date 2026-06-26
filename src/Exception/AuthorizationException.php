<?php

namespace Starmile\PartnerSdk\Exception;

/**
 * HTTP 403 — the credential is valid but lacks the scope required for this call,
 * is revoked, or the Partner API is not enabled for the organization.
 */
class AuthorizationException extends ApiException
{
}
