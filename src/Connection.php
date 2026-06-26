<?php

namespace Starmile\PartnerSdk;

use Starmile\PartnerSdk\Auth\TokenManager;
use Starmile\PartnerSdk\Exception\ApiException;
use Starmile\PartnerSdk\Exception\RateLimitException;
use Starmile\PartnerSdk\Exception\StarmileException;
use Starmile\PartnerSdk\Http\HttpClientInterface;
use Starmile\PartnerSdk\Http\RawResponse;
use Starmile\PartnerSdk\Retry\RealSleeper;
use Starmile\PartnerSdk\Retry\RetryPolicy;
use Starmile\PartnerSdk\Retry\Sleeper;

/**
 * The authenticated JSON transport every resource shares. It attaches the bearer
 * token, encodes/decodes JSON, turns non-2xx responses into typed exceptions,
 * transparently refreshes the token once on a 401, and applies the
 * {@see RetryPolicy} for transient failures (network errors, 429, 5xx).
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

    /** @var RetryPolicy */
    private $retryPolicy;

    /** @var Sleeper */
    private $sleeper;

    public function __construct(
        HttpClientInterface $http,
        TokenManager $tokenManager,
        $baseUrl,
        $userAgent,
        ?RetryPolicy $retryPolicy = null,
        ?Sleeper $sleeper = null
    ) {
        $this->http = $http;
        $this->tokenManager = $tokenManager;
        $this->baseUrl = rtrim((string) $baseUrl, '/');
        $this->userAgent = (string) $userAgent;
        $this->retryPolicy = $retryPolicy === null ? RetryPolicy::disabled() : $retryPolicy;
        $this->sleeper = $sleeper === null ? new RealSleeper() : $sleeper;
    }

    /**
     * Return a copy of this connection that uses a different retry policy
     * (immutable — the original is untouched). Backs {@see Client::retry()}.
     *
     * @return Connection
     */
    public function withRetryPolicy(RetryPolicy $retryPolicy)
    {
        return new self($this->http, $this->tokenManager, $this->baseUrl, $this->userAgent, $retryPolicy, $this->sleeper);
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
     * Perform a request (with retries) and return the decoded JSON body.
     *
     * @param array<string, mixed>      $query
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    public function request($method, $path, array $query = array(), $body = null)
    {
        $attempt = 0;

        while (true) {
            $attempt++;

            try {
                return $this->attempt($method, $path, $query, $body);
            } catch (StarmileException $e) {
                if (!$this->retryPolicy->shouldRetry($method, $e, $attempt)) {
                    throw $e;
                }

                $retryAfter = $e instanceof RateLimitException ? $e->getRetryAfter() : null;
                $this->sleeper->sleepMs($this->retryPolicy->delayFor($attempt, $retryAfter));
            }
        }
    }

    /**
     * A single attempt: dispatch, refresh-and-retry once on 401, decode, and
     * raise a typed exception on a non-2xx status.
     *
     * @param array<string, mixed>      $query
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function attempt($method, $path, array $query, $body)
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
        $exception = ApiException::fromResponse($response->getStatusCode(), $decoded, $response->getBody());

        if ($exception instanceof RateLimitException) {
            $exception->setRetryAfter($response->getHeader('retry-after'));
        }

        return $exception;
    }
}
