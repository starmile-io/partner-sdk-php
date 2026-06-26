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
     * Build the most specific exception for a failed response.
     *
     * @param array<string, mixed> $body
     * @return ApiException
     */
    public static function fromResponse($statusCode, array $body)
    {
        $message = self::extractMessage($body, $statusCode);

        switch ($statusCode) {
            case 401:
                return new AuthenticationException($message, $statusCode, $body);
            case 403:
                return new AuthorizationException($message, $statusCode, $body);
            case 404:
                return new NotFoundException($message, $statusCode, $body);
            case 409:
                return new ConflictException($message, $statusCode, $body);
            case 422:
                return new ValidationException($message, $statusCode, $body);
            case 429:
                return new RateLimitException($message, $statusCode, $body);
            default:
                return new self($message, $statusCode, $body);
        }
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
     * The server's `hint` ("how to fix"), when present (inbound-event rejections).
     *
     * @return string|null
     */
    public function getHint()
    {
        return isset($this->body['hint']) ? $this->body['hint'] : null;
    }
}
