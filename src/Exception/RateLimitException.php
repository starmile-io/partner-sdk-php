<?php

namespace Starmile\PartnerSdk\Exception;

/**
 * HTTP 429 — too many requests. Back off and retry. When the server sends a
 * `Retry-After` header its value (in seconds) is available via {@see getRetryAfter()}.
 */
class RateLimitException extends ApiException
{
    /** @var int|null */
    private $retryAfter;

    /**
     * @return $this
     */
    public function setRetryAfter($seconds)
    {
        $this->retryAfter = $seconds === null ? null : (int) $seconds;

        return $this;
    }

    /**
     * Seconds to wait before retrying, when the server provided a Retry-After header.
     *
     * @return int|null
     */
    public function getRetryAfter()
    {
        return $this->retryAfter;
    }
}
