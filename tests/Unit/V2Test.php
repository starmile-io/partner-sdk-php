<?php

namespace Starmile\PartnerSdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starmile\PartnerSdk\Client;
use Starmile\PartnerSdk\Configuration;
use Starmile\PartnerSdk\Tests\Support\FakeHttpClient;

final class V2Test extends TestCase
{
    public function testCreateSendsTheItemsPayloadToTheV2PathAndUnwrapsData()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(201, array('data' => array(
            'tracking_number' => 'STM000123',
            'order_id' => 'PO-1001',
            'duplicate' => false,
            'region_status' => 'not_applicable',
            'items' => array(array('item_id' => 'BOX-1', 'merchant_tracking' => 'MT-1')),
        )));

        $created = $this->client($http)->v2()->orders()->create(array(
            'service_id' => 7,
            'order_id' => 'PO-1001',
            'customer_email' => 'buyer@example.com',
            'items' => array(array(
                'item_id' => 'BOX-1',
                'merchant_tracking' => 'MT-1',
                'products' => array(array('name' => 'Shoes')),
            )),
        ));

        $this->assertSame('STM000123', $created['tracking_number']);
        $this->assertSame('PO-1001', $created['order_id']);
        $this->assertSame('BOX-1', $created['items'][0]['item_id']);
        // No per-box Starmile tracking exists on v2 — the key is simply absent.
        $this->assertArrayNotHasKey('parcel_id', $created['items'][0]);

        $last = $http->lastRequest();
        $this->assertStringContainsString('/api/v2/orders', $last['url']);
        $body = $http->lastJsonBody();
        $this->assertSame('BOX-1', $body['items'][0]['item_id']);
    }

    public function testStatusPoolPollsTheV2PathAndPages()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(200, array(
            'data' => array(
                array('cursor' => 11, 'tracking_number' => 'STM1', 'order_id' => 'PO-1', 'item_id' => null, 'status' => 'received_at_hub'),
            ),
            'next_cursor' => 11,
            'has_more' => true,
        ));
        $http->queueJson(200, array('data' => array(), 'next_cursor' => 11, 'has_more' => false));

        $rows = iterator_to_array($this->client($http)->v2()->statusPool()->each(0));

        $this->assertCount(1, $rows);
        $this->assertSame('PO-1', $rows[0]['order_id']);

        $last = $http->lastRequest();
        $this->assertStringContainsString('/api/v2/partner/changes', $last['url']);
    }

    public function testSubOrderManagementUsesTheV2SubOrderPaths()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(200, array('data' => array('weight_grams' => 900)));
        $http->queueJson(200, array('data' => array('status' => 'cancelled')));
        $http->queueJson(200, array('data' => array('status' => 'cancelled')));

        $client = $this->client($http);

        $client->v2()->orders()->updateItem('PO-1', 'BOX-1', array('weight_grams' => 900));
        $requests = array($http->lastRequest());

        $client->v2()->orders()->cancelItem('PO-1', 'BOX-1', 'damaged');
        $requests[] = $http->lastRequest();

        $client->v2()->orders()->cancel('PO-1');
        $requests[] = $http->lastRequest();

        $this->assertStringContainsString('/api/v2/orders/PO-1/items/BOX-1', $requests[0]['url']);
        $this->assertSame('PATCH', $requests[0]['method']);
        $this->assertStringContainsString('/api/v2/orders/PO-1/items/BOX-1/cancel', $requests[1]['url']);
        $this->assertStringContainsString('/api/v2/orders/PO-1/cancel', $requests[2]['url']);
    }

    public function testCatalogueAndEventsHitTheV2Paths()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(200, array('data' => array()));
        $http->queueJson(200, array('result' => 'accepted'));

        $client = $this->client($http);
        $client->v2()->catalogue()->services();
        $servicesUrl = $http->lastRequest();

        $client->v2()->events()->reportEvent('parcel.delivered', 'STM1', 'evt-1');
        $eventsUrl = $http->lastRequest();

        $this->assertStringContainsString('/api/v2/services', $servicesUrl['url']);
        $this->assertStringContainsString('/api/v2/partner/events', $eventsUrl['url']);
    }

    private function client(FakeHttpClient $http)
    {
        return new Client(new Configuration('id', 'secret', array('http_client' => $http, 'max_attempts' => 1)));
    }
}
