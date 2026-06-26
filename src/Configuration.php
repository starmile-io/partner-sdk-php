<?php

namespace Starmile\PartnerSdk;

use InvalidArgumentException;
use Starmile\PartnerSdk\Auth\Credentials;
use Starmile\PartnerSdk\Auth\TokenStorageInterface;
use Starmile\PartnerSdk\Http\CurlHttpClient;
use Starmile\PartnerSdk\Http\HttpClientInterface;
use Starmile\PartnerSdk\Retry\RealSleeper;
use Starmile\PartnerSdk\Retry\RetryPolicy;
use Starmile\PartnerSdk\Retry\Sleeper;

/**
 * Immutable configuration for a {@see Client}: the credentials, the API base URL,
 * and the transport details. Nothing environment-specific is hardcoded — the base
 * URL defaults to production but should be supplied per environment (set it to
 * your sandbox/staging host outside production).
 */
final class Configuration
{
    /** The production Partner API base URL (default). */
    const DEFAULT_BASE_URL = 'https://api.starmile.app';

    /** @var Credentials */
    private $credentials;

    /** @var string */
    private $baseUrl;

    /** @var HttpClientInterface */
    private $httpClient;

    /** @var TokenStorageInterface|null */
    private $tokenStorage;

    /** @var string */
    private $userAgent;

    /** @var RetryPolicy */
    private $retryPolicy;

    /** @var Sleeper */
    private $sleeper;

    /**
     * @param array{
     *     base_url?: string,
     *     scopes?: array<int, string>,
     *     http_client?: HttpClientInterface,
     *     token_storage?: TokenStorageInterface,
     *     verify_tls?: bool,
     *     connect_timeout?: int,
     *     timeout?: int,
     *     user_agent?: string,
     *     max_attempts?: int,
     *     retry_base_delay_ms?: int,
     *     retry_max_delay_ms?: int,
     *     sleeper?: Sleeper
     * } $options
     */
    public function __construct($clientId, $clientSecret, array $options = array())
    {
        if ($clientId === '' || $clientId === null) {
            throw new InvalidArgumentException('A Partner API client_id is required.');
        }
        if ($clientSecret === '' || $clientSecret === null) {
            throw new InvalidArgumentException('A Partner API client_secret is required.');
        }

        $scopes = isset($options['scopes']) ? $options['scopes'] : array();
        $this->credentials = new Credentials($clientId, $clientSecret, $scopes);

        $this->baseUrl = rtrim(isset($options['base_url']) ? $options['base_url'] : self::DEFAULT_BASE_URL, '/');

        if (isset($options['http_client'])) {
            $this->httpClient = $options['http_client'];
        } else {
            $verifyTls = isset($options['verify_tls']) ? (bool) $options['verify_tls'] : true;
            $connectTimeout = isset($options['connect_timeout']) ? (int) $options['connect_timeout'] : 10;
            $timeout = isset($options['timeout']) ? (int) $options['timeout'] : 30;
            $this->httpClient = new CurlHttpClient($connectTimeout, $timeout, $verifyTls);
        }

        $this->tokenStorage = isset($options['token_storage']) ? $options['token_storage'] : null;
        $this->userAgent = isset($options['user_agent']) ? $options['user_agent'] : 'starmile-partner-sdk-php/' . Client::VERSION;

        // Default resilience: retry SAFE (GET) requests on transient failures
        // (network, 429, 5xx). Writes are never retried automatically — opt in
        // per call with Client::retry(). max_attempts = 1 disables retries.
        $maxAttempts = isset($options['max_attempts']) ? (int) $options['max_attempts'] : 3;
        $baseDelay = isset($options['retry_base_delay_ms']) ? (int) $options['retry_base_delay_ms'] : 200;
        $maxDelay = isset($options['retry_max_delay_ms']) ? (int) $options['retry_max_delay_ms'] : 5000;
        $this->retryPolicy = RetryPolicy::safeDefault($maxAttempts, $baseDelay, $maxDelay);

        $this->sleeper = isset($options['sleeper']) ? $options['sleeper'] : new RealSleeper();
    }

    /**
     * @return Credentials
     */
    public function getCredentials()
    {
        return $this->credentials;
    }

    /**
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->baseUrl;
    }

    /**
     * @return HttpClientInterface
     */
    public function getHttpClient()
    {
        return $this->httpClient;
    }

    /**
     * @return TokenStorageInterface|null
     */
    public function getTokenStorage()
    {
        return $this->tokenStorage;
    }

    /**
     * @return string
     */
    public function getUserAgent()
    {
        return $this->userAgent;
    }

    /**
     * @return RetryPolicy
     */
    public function getRetryPolicy()
    {
        return $this->retryPolicy;
    }

    /**
     * @return Sleeper
     */
    public function getSleeper()
    {
        return $this->sleeper;
    }
}
