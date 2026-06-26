<?php

namespace Starmile\PartnerSdk\Exception;

use Exception;

/**
 * Thrown when the Partner API returns a non-2xx response. Carries the HTTP status
 * code and the decoded JSON body so callers can inspect the server's explanation
 * (`message`, `error`, `hint`, or a field-level `errors` map).
 *
 * Use {@see ApiException::fromResponse()} to get the most specific subclass for a
 * given status (401 → AuthenticationException, 403 → AuthorizationException, etc.).
 */
class ApiException extends StarmileException
{
    /** @var int */
    protected $statusCode;

    /** @var array<string, mixed> */
    protected $body;

    /** @var string|null The undecoded response body (preserved even when not JSON). */
    protected $rawBody;

    /**
     * @param array<string, mixed> $body
     */
    public function __construct($message, $statusCode, array $body = array(), ?Exception $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->body = $body;
    }

    /**
     * Build the most specific exception for a failed response. `$rawBody` is the
     * undecoded response string, kept so an opaque error page (a gateway's HTML
     * 502, an empty 500) is still inspectable via {@see getRawBody()}.
     *
     * @param array<string, mixed> $body
     * @return ApiException
     */
    public static function fromResponse($statusCode, array $body, $rawBody = null)
    {
        $message = self::extractMessage($body, $statusCode);

        switch ($statusCode) {
            case 401:
                $exception = new AuthenticationException($message, $statusCode, $body);
                break;
            case 403:
                $exception = new AuthorizationException($message, $statusCode, $body);
                break;
            case 404:
                $exception = new NotFoundException($message, $statusCode, $body);
                break;
            case 409:
                $exception = new ConflictException($message, $statusCode, $body);
                break;
            case 422:
                $exception = new ValidationException($message, $statusCode, $body);
                break;
            case 429:
                $exception = new RateLimitException($message, $statusCode, $body);
                break;
            default:
                $exception = new self($message, $statusCode, $body);
        }

        $exception->rawBody = $rawBody === null ? null : (string) $rawBody;

        return $exception;
    }

    /**
     * @param array<string, mixed> $body
     * @return string
     */
    protected static function extractMessage(array $body, $statusCode)
    {
        if (isset($body['message']) && is_string($body['message']) && $body['message'] !== '') {
            return $body['message'];
        }

        // Inbound-event rejections carry `error` + `hint` instead of `message`.
        if (isset($body['error']) && is_string($body['error']) && $body['error'] !== '') {
            $message = $body['error'];

            if (isset($body['hint']) && is_string($body['hint']) && $body['hint'] !== '') {
                $message .= ' (' . $body['hint'] . ')';
            }

            return $message;
        }

        if (isset($body['error_description']) && is_string($body['error_description'])) {
            return $body['error_description'];
        }

        return 'Starmile Partner API request failed with status ' . $statusCode . '.';
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * The full decoded response body.
     *
     * @return array<string, mixed>
     */
    public function getResponseBody()
    {
        return $this->body;
    }

    /**
     * The undecoded response body, when available. Useful when the server (or a
     * proxy in front of it) returned a non-JSON error the decoder couldn't parse.
     *
     * @return string|null
     */
    public function getRawBody()
    {
        return $this->rawBody;
    }

    /**
     * The server's `hint` ("how to fix"), when present (inbound-event rejections).
     *
     * @return string|null
     */
    public function getHint()
    {
        return isset($this->body['hint']) ? $this->body['hint'] : null;
    }
}
