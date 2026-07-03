<?php

namespace Starmile\PartnerSdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starmile\PartnerSdk\Client;
use Starmile\PartnerSdk\Configuration;
use Starmile\PartnerSdk\Tests\Support\FakeHttpClient;

final class ClientTest extends TestCase
{
    public function testItObtainsATokenThenCallsTheCatalogueWithABearerHeader()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('token_type' => 'Bearer', 'access_token' => 'tok_123', 'expires_in' => 3600));
        $http->queueJson(200, array('data' => array(array('id' => 7, 'name' => 'Express'))));

        $client = $this->client($http);
        $services = $client->catalogue()->services();

        $this->assertSame(array(array('id' => 7, 'name' => 'Express')), $services);

        // First request is the token exchange, form-encoded.
        $token = $http->requests[0];
        $this->assertSame('POST', $token['method']);
        $this->assertSame('https://api.starmile.io/oauth/token', $token['url']);
        $this->assertStringContainsString('client_credentials', $token['body']);

        // Second request carries the bearer token and hits the catalogue.
        $call = $http->requests[1];
        $this->assertSame('https://api.starmile.io/api/v1/services', $call['url']);
        $this->assertSame('Bearer tok_123', $call['headers']['Authorization']);
    }

    public function testItReusesTheCachedTokenAcrossCalls()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok_abc', 'expires_in' => 3600));
        $http->queueJson(200, array('data' => array()));
        $http->queueJson(200, array('data' => array()));

        $client = $this->client($http);
        $client->catalogue()->services();
        $client->catalogue()->rates();

        // 1 token call + 2 API calls — the token was not re-fetched.
        $this->assertCount(3, $http->requests);
        $this->assertSame('https://api.starmile.io/oauth/token', $http->requests[0]['url']);
        $this->assertSame('https://api.starmile.io/api/v1/services', $http->requests[1]['url']);
        $this->assertSame('https://api.starmile.io/api/v1/rates', $http->requests[2]['url']);
    }

    public function testABaseUrlOverrideIsHonoured()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(200, array('data' => array()));

        $client = Client::create('id', 'secret', array(
            'base_url' => 'https://sandbox.example.test',
            'http_client' => $http,
        ));
        $client->catalogue()->services();

        $this->assertSame('https://sandbox.example.test/oauth/token', $http->requests[0]['url']);
        $this->assertSame('https://sandbox.example.test/api/v1/services', $http->requests[1]['url']);
    }

    private function client(FakeHttpClient $http)
    {
        return new Client(new Configuration('id', 'secret', array('http_client' => $http, 'max_attempts' => 1)));
    }
}
