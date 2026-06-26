<?php

namespace Starmile\PartnerSdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starmile\PartnerSdk\Client;
use Starmile\PartnerSdk\Configuration;
use Starmile\PartnerSdk\Enum\EventType;
use Starmile\PartnerSdk\Enum\Scope;
use Starmile\PartnerSdk\Exception\ApiException;
use Starmile\PartnerSdk\Exception\AuthenticationException;
use Starmile\PartnerSdk\Exception\AuthorizationException;
use Starmile\PartnerSdk\Exception\NotFoundException;
use Starmile\PartnerSdk\Exception\RateLimitException;
use Starmile\PartnerSdk\Tests\Support\FakeHttpClient;

final class TokenAndErrorsTest extends TestCase
{
    public function testA401TriggersOneTokenRefreshAndRetry()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'stale', 'expires_in' => 3600)); // initial token
        $http->queueJson(401, array('message' => 'Unauthenticated.'));                  // first API call rejected
        $http->queueJson(200, array('access_token' => 'fresh', 'expires_in' => 3600)); // forced refresh
        $http->queueJson(200, array('data' => array(array('id' => 1))));                // retry succeeds

        $services = $this->client($http)->catalogue()->services();

        $this->assertSame(array(array('id' => 1)), $services);
        $this->assertCount(4, $http->requests);
        // The retry used the refreshed token.
        $this->assertSame('Bearer fresh', $http->requests[3]['headers']['Authorization']);
    }

    public function testRateLimitExceptionCarriesRetryAfter()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueRaw(429, json_encode(array('message' => 'Too Many Requests.')), array('Retry-After' => '12'));

        try {
            $this->client($http)->catalogue()->services();
            $this->fail('Expected RateLimitException.');
        } catch (RateLimitException $e) {
            $this->assertSame(429, $e->getStatusCode());
            $this->assertSame(12, $e->getRetryAfter());
        }
    }

    public function testExceptionMappingBySatusCode()
    {
        $this->assertInstanceOf(AuthenticationException::class, ApiException::fromResponse(401, array()));
        $this->assertInstanceOf(AuthorizationException::class, ApiException::fromResponse(403, array()));
        $this->assertInstanceOf(NotFoundException::class, ApiException::fromResponse(404, array()));
        $this->assertInstanceOf(ApiException::class, ApiException::fromResponse(500, array()));
    }

    public function testEventDataFieldsIncludeNoteAndScopeIsKnown()
    {
        $this->assertContains('note', EventType::dataFieldsFor(EventType::SHIPMENT_DELIVERED));
        $this->assertContains('proof_of_delivery', EventType::dataFieldsFor(EventType::SHIPMENT_DELIVERED));
        $this->assertContains(Scope::ORDERS_CREATE, Scope::all());
        $this->assertTrue(EventType::isValid(EventType::CUSTOMS_HELD));
        $this->assertFalse(EventType::isValid('customs.cleared'));
    }

    private function client(FakeHttpClient $http)
    {
        return new Client(new Configuration('id', 'secret', array('http_client' => $http, 'max_attempts' => 1)));
    }
}
