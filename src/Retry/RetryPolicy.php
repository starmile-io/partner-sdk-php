<?php

namespace Starmile\PartnerSdk\Retry;

use Starmile\PartnerSdk\Exception\ApiException;
use Starmile\PartnerSdk\Exception\StarmileException;
use Starmile\PartnerSdk\Exception\TransportException;

/**
 * Decides whether a failed request should be retried and how long to wait first.
 *
 * Two presets cover the SDK's needs:
 *  - {@see safeDefault()} — the default policy. Retries only **safe** (GET)
 *    requests, and only on transient failures (network errors, 429, 5xx). A
 *    non-idempotent write (POST /orders, POST /partner/events) is never retried
 *    automatically, so a flaky response can't create a duplicate order.
 *  - {@see custom()} — what `Client::retry()` builds: an explicit, caller-chosen
 *    policy that DOES retry writes (the caller opted in) and can carry a custom
 *    decision callback, mirroring Laravel's HTTP client `retry()`.
 *
 * Backoff is exponential (base · 2^n) capped at a maximum, with ±20% jitter; a
 * `Retry-After` header (on a 429) overrides the computed delay.
 */
final class RetryPolicy
{
    /** @var int Total attempts including the first (1 = no retry). */
    private $maxAttempts;

    /** @var int */
    private $baseDelayMs;

    /** @var int */
    private $maxDelayMs;

    /** @var bool Whether non-idempotent methods (POST/PATCH/...) may be retried. */
    private $retryWrites;

    /** @var callable|null Custom "should retry" decision: fn(StarmileException, int $attempt): bool. */
    private $when;

    public function __construct($maxAttempts = 1, $baseDelayMs = 200, $maxDelayMs = 5000, $retryWrites = false, ?callable $when = null)
    {
        $this->maxAttempts = max(1, (int) $maxAttempts);
        $this->baseDelayMs = max(0, (int) $baseDelayMs);
        $this->maxDelayMs = max(0, (int) $maxDelayMs);
        $this->retryWrites = (bool) $retryWrites;
        $this->when = $when;
    }

    /**
     * A policy that never retries.
     *
     * @return RetryPolicy
     */
    public static function disabled()
    {
        return new self(1);
    }

    /**
     * The default: retry safe (GET) requests on transient failures only.
     *
     * @return RetryPolicy
     */
    public static function safeDefault($maxAttempts = 3, $baseDelayMs = 200, $maxDelayMs = 5000)
    {
        return new self($maxAttempts, $baseDelayMs, $maxDelayMs, false, null);
    }

    /**
     * A caller-chosen policy (from `Client::retry()`). Applies to writes too,
     * because the caller explicitly asked for it.
     *
     * @param int           $times   Total attempts (Laravel-style).
     * @param int           $sleepMs Base backoff in milliseconds.
     * @param callable|null $when    Optional fn(StarmileException, int $attempt): bool.
     * @return RetryPolicy
     */
    public static function custom($times, $sleepMs = 100, ?callable $when = null)
    {
        $sleepMs = max(0, (int) $sleepMs);

        return new self($times, $sleepMs, max($sleepMs, 30000), true, $when);
    }

    /**
     * @param string             $method  The HTTP method of the request.
     * @param StarmileException  $e       The failure raised by the attempt.
     * @param int                $attempt The attempt number that just failed (1-based).
     * @return bool
     */
    public function shouldRetry($method, StarmileException $e, $attempt)
    {
        if ($attempt >= $this->maxAttempts) {
            return false;
        }

        if (!$this->methodAllowed($method)) {
            return false;
        }

        return $this->isRetryable($e, $attempt);
    }

    /**
     * The delay (milliseconds) to wait before the next attempt.
     *
     * @param int      $attempt           The attempt number that just failed (1-based).
     * @param int|null $retryAfterSeconds A server Retry-After value, if any.
     * @return int
     */
    public function delayFor($attempt, $retryAfterSeconds = null)
    {
        if ($retryAfterSeconds !== null && $retryAfterSeconds > 0) {
            return (int) $retryAfterSeconds * 1000;
        }

        $exponential = $this->baseDelayMs * (2 ** ((int) $attempt - 1));
        $delay = (int) min($exponential, $this->maxDelayMs);

        $jitter = (int) ($delay * 0.2);
        if ($jitter > 0) {
            $delay = $delay - $jitter + mt_rand(0, 2 * $jitter);
        }

        return max(0, $delay);
    }

    /**
     * @return bool
     */
    private function methodAllowed($method)
    {
        if ($this->retryWrites) {
            return true;
        }

        return strtoupper((string) $method) === 'GET';
    }

    /**
     * @return bool
     */
    private function isRetryable(StarmileException $e, $attempt)
    {
        if ($this->when !== null) {
            return (bool) call_user_func($this->when, $e, $attempt);
        }

        if ($e instanceof TransportException) {
            return true;
        }

        if ($e instanceof ApiException) {
            $status = $e->getStatusCode();

            return $status === 429 || $status >= 500;
        }

        return false;
    }
}
