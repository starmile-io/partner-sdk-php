<?php

namespace Starmile\PartnerSdk;

use Starmile\PartnerSdk\Auth\TokenManager;
use Starmile\PartnerSdk\Exception\ApiException;
use Starmile\PartnerSdk\Exception\RateLimitException;
use Starmile\PartnerSdk\Http\HttpClientInterface;
use Starmile\PartnerSdk\Http\RawResponse;

/**
 * The authenticated JSON transport every resource shares. It attaches the bearer
 * token, encodes/decodes JSON, turns non-2xx responses into typed exceptions, and
 * transparently refreshes the token once on a 401.
 *
 * Not part of the public API — obtain resources from {@see Client} instead.
 */
final class Connection
{
    /** @var HttpClientInterface */
    private $http;

    /** @var TokenManager */
    private $tokenManager;

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $userAgent;

    public function __construct(HttpClientInterface $http, TokenManager $tokenManager, $baseUrl, $userAgent)
    {
        $this->http = $http;
        $this->tokenManager = $tokenManager;
        $this->baseUrl = rtrim((string) $baseUrl, '/');
        $this->userAgent = (string) $userAgent;
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function get($path, array $query = array())
    {
        return $this->request('GET', $path, $query, null);
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    public function post($path, $body = null)
    {
        return $this->request('POST', $path, array(), $body);
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    public function patch($path, $body = null)
    {
        return $this->request('PATCH', $path, array(), $body);
    }

    /**
     * Perform a request and return the decoded JSON body. Retries once after a
     * forced token refresh if the first attempt is rejected with 401.
     *
     * @param array<string, mixed>      $query
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    public function request($method, $path, array $query = array(), $body = null)
    {
        $response = $this->dispatch($method, $path, $query, $body, false);

        if ($response->getStatusCode() === 401) {
            // The token looked valid but the server disagreed — refresh and retry once.
            $response = $this->dispatch($method, $path, $query, $body, true);
        }

        $decoded = $this->decode($response);

        if (!$response->isSuccessful()) {
            throw $this->toException($response, $decoded);
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed>      $query
     * @param array<string, mixed>|null $body
     * @return RawResponse
     */
    private function dispatch($method, $path, array $query, $body, $forceRefresh)
    {
        $token = $forceRefresh ? $this->tokenManager->refresh() : $this->tokenManager->getToken();

        $url = $this->baseUrl . $path;
        if ($query !== array()) {
            $url .= '?' . http_build_query($query);
        }

        $headers = array(
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
            'User-Agent' => $this->userAgent,
        );

        $payload = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $payload = json_encode($body);
        }

        return $this->http->send($method, $url, $headers, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(RawResponse $response)
    {
        $body = $response->getBody();

        if ($body === '') {
            return array();
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : array();
    }

    /**
     * @param array<string, mixed> $decoded
     * @return ApiException
     */
    private function toException(RawResponse $response, array $decoded)
    {
        $exception = ApiException::fromResponse($response->getStatusCode(), $decoded);

        if ($exception instanceof RateLimitException) {
            $exception->setRetryAfter($response->getHeader('retry-after'));
        }

        return $exception;
    }
}
