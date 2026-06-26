<?php

namespace Starmile\PartnerSdk\Auth;

/**
 * The OAuth2 client-credentials a partner exchanges for a bearer token. Issued by
 * the organization's back office (a Partner API credential = client_id +
 * client_secret). Keep the secret server-side; never expose it to a browser or app.
 */
final class Credentials
{
    /** @var string */
    private $clientId;

    /** @var string */
    private $clientSecret;

    /** @var array<int, string> */
    private $scopes;

    /**
     * @param array<int, string> $scopes Optional subset of the credential's scopes
     *                                    to request on the token (empty = all granted).
     */
    public function __construct($clientId, $clientSecret, array $scopes = array())
    {
        $this->clientId = (string) $clientId;
        $this->clientSecret = (string) $clientSecret;
        $this->scopes = $scopes;
    }

    /**
     * @return string
     */
    public function getClientId()
    {
        return $this->clientId;
    }

    /**
     * @return string
     */
    public function getClientSecret()
    {
        return $this->clientSecret;
    }

    /**
     * @return array<int, string>
     */
    public function getScopes()
    {
        return $this->scopes;
    }
}
