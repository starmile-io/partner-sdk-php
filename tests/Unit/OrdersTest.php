<?php

namespace Starmile\PartnerSdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starmile\PartnerSdk\Builder\OrderBuilder;
use Starmile\PartnerSdk\Builder\ProductBuilder;
use Starmile\PartnerSdk\Builder\ShipmentBuilder;
use Starmile\PartnerSdk\Client;
use Starmile\PartnerSdk\Configuration;
use Starmile\PartnerSdk\Exception\ConflictException;
use Starmile\PartnerSdk\Exception\ValidationException;
use Starmile\PartnerSdk\Tests\Support\FakeHttpClient;

final class OrdersTest extends TestCase
{
    public function testCreateSendsTheBuilderPayloadAndUnwrapsData()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(201, array('data' => array('id' => 99, 'tracking_number' => 'SM123')));

        $order = OrderBuilder::make(7, 'ORD-1')
            ->recipient('Jane Doe', '+994500000000', null, '5AB12C3')
            ->deliverToPudo(42)
            ->addShipment(
                ShipmentBuilder::make('ITEM-1')
                    ->merchantTracking('BC-1')
                    ->addProduct(ProductBuilder::make('Shoes')->declaredValue(50, 'USD')->quantity(1))
            );

        $created = $this->client($http)->orders()->create($order);

        $this->assertSame(array('id' => 99, 'tracking_number' => 'SM123'), $created);

        $body = $http->lastJsonBody();
        $this->assertSame(7, $body['service_id']);
        $this->assertSame('ORD-1', $body['order_id']);
        $this->assertSame('pudo', $body['delivery']);
        $this->assertSame(42, $body['pudo_id']);
        // The recipient government ID is sent as gov_id (was customer_pin).
        $this->assertSame('5AB12C3', $body['gov_id']);
        $this->assertArrayNotHasKey('customer_pin', $body);
        $this->assertSame('ITEM-1', $body['shipments'][0]['item_id']);
        $this->assertSame('Shoes', $body['shipments'][0]['products'][0]['name']);
        $this->assertSame('USD', $body['shipments'][0]['products'][0]['currency']);
    }

    public function testDeliverHomeToRegionSendsTheRegionNameForServerResolution()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(201, array('data' => array('id' => 1, 'tracking_number' => 'SM1')));

        // A partner that only knows the destination region by its human name sends
        // it as-is; the API resolves it (exact name, then id) to a region id.
        $order = OrderBuilder::make(7, 'ORD-2')
            ->deliverHomeToRegion('Abşeron', 'Nizami küç. 12', 'Apt 4', 'AZ1000')
            ->addShipment(ShipmentBuilder::make('ITEM-1')->addProduct(ProductBuilder::make('Shoes')));

        $this->client($http)->orders()->create($order);

        $body = $http->lastJsonBody();
        $this->assertSame('home', $body['delivery']);
        $this->assertSame('Abşeron', $body['region']);
        $this->assertArrayNotHasKey('region_id', $body);
        $this->assertSame('Nizami küç. 12', $body['address_first']);
        $this->assertSame('AZ1000', $body['zip']);
    }

    public function testUpdateShipmentEncodesReferencesInThePath()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(200, array('data' => array('id' => 1, 'status' => 'expected')));

        $this->client($http)->orders()->updateShipment('ORD/1', 'ITEM 2', array('weight_grams' => 1200));

        $call = $http->lastRequest();
        $this->assertSame('PATCH', $call['method']);
        $this->assertSame('https://api.starmile.app/api/v1/orders/ORD%2F1/shipments/ITEM%202', $call['url']);
        $this->assertSame(array('weight_grams' => 1200), $http->lastJsonBody());
    }

    public function testCancelRaisesConflictWhenInCustody()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(409, array('message' => 'Order is already in custody.'));

        $this->expectException(ConflictException::class);
        $this->client($http)->orders()->cancel('ORD-1', 'changed mind');
    }

    public function testValidationErrorsAreExposed()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(422, array(
            'message' => 'The given data was invalid.',
            'errors' => array('service_id' => array('The service id field is required.')),
        ));

        try {
            $this->client($http)->orders()->create(array('order_id' => 'X'));
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertArrayHasKey('service_id', $e->errors());
            $this->assertSame(array('The service id field is required.'), $e->allMessages());
        }
    }

    private function client(FakeHttpClient $http)
    {
        return new Client(new Configuration('id', 'secret', array('http_client' => $http, 'max_attempts' => 1)));
    }
}
