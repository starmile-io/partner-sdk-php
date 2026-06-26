<?php

namespace Starmile\PartnerSdk\Auth;

use Starmile\PartnerSdk\Exception\ApiException;
use Starmile\PartnerSdk\Http\HttpClientInterface;

/**
 * Obtains and caches the OAuth2 client-credentials bearer token. Exchanges the
 * {@see Credentials} at `POST /oauth/token`, caches the result in a
 * {@see TokenStorageInterface}, and transparently re-fetches when it expires.
 */
final class TokenManager
{
    /** @var HttpClientInterface */
    private $http;

    /** @var string */
    private $baseUrl;

    /** @var Credentials */
    private $credentials;

    /** @var TokenStorageInterface */
    private $storage;

    public function __construct(
        HttpClientInterface $http,
        $baseUrl,
        Credentials $credentials,
        ?TokenStorageInterface $storage = null
    ) {
        $this->http = $http;
        $this->baseUrl = rtrim((string) $baseUrl, '/');
        $this->credentials = $credentials;
        $this->storage = $storage === null ? new InMemoryTokenStorage() : $storage;
    }

    /**
     * The current bearer token string, fetching or refreshing as needed.
     *
     * @return string
     */
    public function getToken()
    {
        $token = $this->storage->get();

        if ($token !== null && $token->isValid()) {
            return $token->getToken();
        }

        return $this->fetch()->getToken();
    }

    /**
     * Force a fresh token (e.g. after a 401 with a token that looked valid).
     *
     * @return string
     */
    public function refresh()
    {
        $this->storage->clear();

        return $this->fetch()->getToken();
    }

    /**
     * @return AccessToken
     */
    private function fetch()
    {
        $form = array(
            'grant_type' => 'client_credentials',
            'client_id' => $this->credentials->getClientId(),
            'client_secret' => $this->credentials->getClientSecret(),
        );

        $scopes = $this->credentials->getScopes();
        if ($scopes !== array()) {
            $form['scope'] = implode(' ', $scopes);
        }

        $response = $this->http->send(
            'POST',
            $this->baseUrl . '/oauth/token',
            array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ),
            http_build_query($form)
        );

        $body = json_decode($response->getBody(), true);
        if (!is_array($body)) {
            $body = array();
        }

        if (!$response->isSuccessful()) {
            throw ApiException::fromResponse($response->getStatusCode(), $body, $response->getBody());
        }

        $token = AccessToken::fromTokenResponse($body);
        $this->storage->set($token);

        return $token;
    }
}
