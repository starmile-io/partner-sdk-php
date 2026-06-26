<?php

namespace Starmile\PartnerSdk\Tests\Support;

use Starmile\PartnerSdk\Http\HttpClientInterface;
use Starmile\PartnerSdk\Http\RawResponse;

/**
 * A scripted HTTP transport for tests: queue the responses to return (in order)
 * and inspect the requests that were sent.
 */
final class FakeHttpClient implements HttpClientInterface
{
    /** @var array<int, RawResponse> */
    private $queue = array();

    /** @var array<int, array<string, mixed>> */
    public $requests = array();

    /**
     * Queue a JSON response.
     *
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     * @return $this
     */
    public function queueJson($statusCode, array $body, array $headers = array())
    {
        $headers['Content-Type'] = 'application/json';
        $this->queue[] = new RawResponse($statusCode, $headers, json_encode($body));

        return $this;
    }

    /**
     * Queue a raw response.
     *
     * @param array<string, string> $headers
     * @return $this
     */
    public function queueRaw($statusCode, $body, array $headers = array())
    {
        $this->queue[] = new RawResponse($statusCode, $headers, $body);

        return $this;
    }

    public function send($method, $url, array $headers = array(), $body = null)
    {
        $this->requests[] = array(
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
            'body' => $body,
        );

        if ($this->queue === array()) {
            return new RawResponse(200, array('Content-Type' => 'application/json'), '{}');
        }

        return array_shift($this->queue);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastRequest()
    {
        $count = count($this->requests);

        return $count > 0 ? $this->requests[$count - 1] : null;
    }

    /**
     * The decoded JSON body of the last request.
     *
     * @return array<string, mixed>
     */
    public function lastJsonBody()
    {
        $last = $this->lastRequest();
        if ($last === null || $last['body'] === null) {
            return array();
        }

        $decoded = json_decode($last['body'], true);

        return is_array($decoded) ? $decoded : array();
    }
}
