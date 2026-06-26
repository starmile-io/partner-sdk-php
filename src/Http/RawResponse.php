<?php

namespace Starmile\PartnerSdk\Http;

/**
 * A transport-agnostic HTTP response: the status code, the response headers
 * (lower-cased keys), and the raw body string. The SDK decodes JSON itself, so a
 * custom {@see HttpClientInterface} only has to return these three things.
 */
final class RawResponse
{
    /** @var int */
    private $statusCode;

    /** @var array<string, string> */
    private $headers;

    /** @var string */
    private $body;

    /**
     * @param array<string, string> $headers
     */
    public function __construct($statusCode, array $headers, $body)
    {
        $this->statusCode = (int) $statusCode;
        $this->headers = self::normalizeHeaders($headers);
        $this->body = (string) $body;
    }

    /**
     * @return int
     */
    public function getStatusCode()
    {
        return $this->statusCode;
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @return string|null
     */
    public function getHeader($name)
    {
        $name = strtolower($name);

        return isset($this->headers[$name]) ? $this->headers[$name] : null;
    }

    /**
     * @return bool
     */
    public function isSuccessful()
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private static function normalizeHeaders(array $headers)
    {
        $normalized = array();

        foreach ($headers as $key => $value) {
            $normalized[strtolower((string) $key)] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return $normalized;
    }
}
