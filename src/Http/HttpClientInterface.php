<?php

namespace Starmile\PartnerSdk\Http;

/**
 * The minimal HTTP transport contract the SDK depends on. The bundled
 * {@see CurlHttpClient} is the default; implement this interface to route requests
 * through Guzzle, Symfony HttpClient, a PSR-18 client, or a test double.
 */
interface HttpClientInterface
{
    /**
     * Send a request and return the raw response. Implementations MUST throw a
     * {@see \Starmile\PartnerSdk\Exception\TransportException} on a network-level
     * failure (no response). They MUST NOT throw on a non-2xx HTTP status — that is
     * the SDK's responsibility to interpret.
     *
     * @param string                $method  HTTP method (uppercase).
     * @param string                $url     The fully-qualified URL.
     * @param array<string, string> $headers Header name => value.
     * @param string|null           $body    The raw request body, or null.
     * @return RawResponse
     */
    public function send($method, $url, array $headers = array(), $body = null);
}
