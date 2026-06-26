<?php

namespace Starmile\PartnerSdk\Http;

use Starmile\PartnerSdk\Exception\TransportException;

/**
 * The default HTTP transport, built on ext-curl only — no third-party HTTP
 * dependency, so the SDK installs cleanly on legacy PHP. TLS verification is on by
 * default and must stay on in production.
 */
final class CurlHttpClient implements HttpClientInterface
{
    /** @var int Connection timeout in seconds. */
    private $connectTimeout;

    /** @var int Total request timeout in seconds. */
    private $timeout;

    /** @var bool */
    private $verifyTls;

    public function __construct($connectTimeout = 10, $timeout = 30, $verifyTls = true)
    {
        $this->connectTimeout = (int) $connectTimeout;
        $this->timeout = (int) $timeout;
        $this->verifyTls = (bool) $verifyTls;
    }

    /**
     * {@inheritDoc}
     */
    public function send($method, $url, array $headers = array(), $body = null)
    {
        $handle = curl_init();

        $curlHeaders = array();
        foreach ($headers as $name => $value) {
            $curlHeaders[] = $name . ': ' . $value;
        }

        curl_setopt($handle, CURLOPT_URL, $url);
        curl_setopt($handle, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_HEADER, true);
        curl_setopt($handle, CURLOPT_HTTPHEADER, $curlHeaders);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($handle, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, $this->verifyTls);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, $this->verifyTls ? 2 : 0);

        if ($body !== null && $body !== '') {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($handle);

        if ($raw === false) {
            $error = curl_error($handle);
            $errno = curl_errno($handle);
            curl_close($handle);

            throw new TransportException('HTTP transport error: ' . $error, $errno);
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        curl_close($handle);

        $rawHeaders = substr($raw, 0, $headerSize);
        $responseBody = (string) substr($raw, $headerSize);

        return new RawResponse($statusCode, $this->parseHeaders($rawHeaders), $responseBody);
    }

    /**
     * @return array<string, string>
     */
    private function parseHeaders($rawHeaders)
    {
        $headers = array();

        // The last header block wins (handles redirects / 100-Continue preludes).
        $blocks = preg_split("/\r\n\r\n/", trim((string) $rawHeaders));
        $lastBlock = is_array($blocks) ? end($blocks) : $rawHeaders;

        foreach (preg_split("/\r\n/", (string) $lastBlock) as $line) {
            $position = strpos($line, ':');

            if ($position !== false) {
                $name = trim(substr($line, 0, $position));
                $value = trim(substr($line, $position + 1));
                $headers[$name] = $value;
            }
        }

        return $headers;
    }
}
