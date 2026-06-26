<?php

namespace Starmile\PartnerSdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starmile\PartnerSdk\Client;
use Starmile\PartnerSdk\Configuration;
use Starmile\PartnerSdk\Exception\ApiException;
use Starmile\PartnerSdk\Exception\ConflictException;
use Starmile\PartnerSdk\Tests\Support\FakeHttpClient;
use Starmile\PartnerSdk\Tests\Support\RecordingSleeper;

final class RetryTest extends TestCase
{
    public function testSafeGetIsRetriedOnA503ThenSucceeds()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(503, array('message' => 'Service Unavailable'));
        $http->queueJson(200, array('data' => array(array('id' => 1))));

        $sleeper = new RecordingSleeper();
        $services = $this->client($http, $sleeper)->catalogue()->services();

        $this->assertSame(array(array('id' => 1)), $services);
        $this->assertSame(1, $sleeper->count()); // one backoff between the two attempts
        $this->assertCount(3, $http->requests);  // token + 503 + 200
    }

    public function testGetGivesUpAfterMaxAttempts()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(500, array('message' => 'Boom'));
        $http->queueJson(500, array('message' => 'Boom'));
        $http->queueJson(500, array('message' => 'Boom'));

        $sleeper = new RecordingSleeper();

        try {
            $this->client($http, $sleeper, array('max_attempts' => 3))->catalogue()->services();
            $this->fail('Expected ApiException.');
        } catch (ApiException $e) {
            $this->assertSame(500, $e->getStatusCode());
            $this->assertSame(2, $sleeper->count()); // slept before attempts 2 and 3, then gave up
        }
    }

    public function testWritesAreNotRetriedByDefault()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(503, array('message' => 'Service Unavailable'));

        $sleeper = new RecordingSleeper();

        try {
            $this->client($http, $sleeper)->orders()->create(array('order_id' => 'X'));
            $this->fail('Expected ApiException.');
        } catch (ApiException $e) {
            $this->assertSame(503, $e->getStatusCode());
            $this->assertSame(0, $sleeper->count());   // never retried
            $this->assertCount(2, $http->requests);    // token + the single POST
        }
    }

    public function testRetryOptInRetriesWrites()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(503, array('message' => 'Service Unavailable'));
        $http->queueJson(201, array('data' => array('tracking_number' => 'STM1')));

        $sleeper = new RecordingSleeper();

        $created = $this->client($http, $sleeper)
            ->retry(3, 50)
            ->orders()
            ->create(array('order_id' => 'X'));

        $this->assertSame('STM1', $created['tracking_number']);
        $this->assertSame(1, $sleeper->count());
    }

    public function testRetryAfterHeaderDrivesTheDelayOn429()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueRaw(429, json_encode(array('message' => 'Slow down')), array('Retry-After' => '2'));
        $http->queueJson(200, array('data' => array()));

        $sleeper = new RecordingSleeper();
        $this->client($http, $sleeper)->catalogue()->services();

        $this->assertSame(array(2000), $sleeper->sleeps); // honored Retry-After: 2s
    }

    public function testCustomWhenCallbackCanRetryAConflict()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(409, array('message' => 'Locked, try again'));
        $http->queueJson(200, array('data' => array('tracking_number' => 'STM2')));

        $sleeper = new RecordingSleeper();

        $when = function ($e) {
            return $e instanceof ConflictException;
        };

        $result = $this->client($http, $sleeper)
            ->retry(3, 10, $when)
            ->orders()
            ->cancel('ORD-1');

        $this->assertSame('STM2', $result['tracking_number']);
        $this->assertSame(1, $sleeper->count());
    }

    public function testRawBodyIsPreservedForNonJsonErrors()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueRaw(502, '<html><body>Bad Gateway</body></html>', array('Content-Type' => 'text/html'));

        // max_attempts=1 disables retries, so the 502 surfaces immediately.
        try {
            $this->client($http, new RecordingSleeper(), array('max_attempts' => 1))->catalogue()->services();
            $this->fail('Expected ApiException.');
        } catch (ApiException $e) {
            $this->assertSame(502, $e->getStatusCode());
            $this->assertSame(array(), $e->getResponseBody());            // not JSON
            $this->assertStringContainsString('Bad Gateway', $e->getRawBody());
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function client(FakeHttpClient $http, RecordingSleeper $sleeper, array $options = array())
    {
        $options['http_client'] = $http;
        $options['sleeper'] = $sleeper;

        return new Client(new Configuration('id', 'secret', $options));
    }
}
