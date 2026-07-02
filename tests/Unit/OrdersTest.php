<?php

namespace Starmile\PartnerSdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starmile\PartnerSdk\Builder\OrderBuilder;
use Starmile\PartnerSdk\Builder\ProductBuilder;
use Starmile\PartnerSdk\Builder\ParcelBuilder;
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
            ->addParcel(
                ParcelBuilder::make('ITEM-1')
                    ->merchantTracking('BC-1')
                    ->addProduct(ProductBuilder::make('Shoes')->declaredValue(50, 'USD')->quantity(1))
            );

        $created = $this->client($http)->orders()->create($order);

        $this->assertSame(array('id' => 99, 'tracking_number' => 'SM123'), $created);

        $body = $http->lastJsonBody();
        $this->assertSame(7, $body['service_id']);
        $this->assertSame('ORD-1', $body['order_id']);
        // The delivery channel is bound to the Service (delivery_type), not sent.
        $this->assertArrayNotHasKey('delivery', $body);
        $this->assertSame(42, $body['pudo_id']);
        // The recipient government ID is sent as gov_id (was customer_pin).
        $this->assertSame('5AB12C3', $body['gov_id']);
        $this->assertArrayNotHasKey('customer_pin', $body);
        $this->assertSame('ITEM-1', $body['parcels'][0]['item_id']);
        $this->assertSame('Shoes', $body['parcels'][0]['products'][0]['name']);
        $this->assertSame('USD', $body['parcels'][0]['products'][0]['currency']);
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
            ->addParcel(ParcelBuilder::make('ITEM-1')->addProduct(ProductBuilder::make('Shoes')));

        $this->client($http)->orders()->create($order);

        $body = $http->lastJsonBody();
        $this->assertArrayNotHasKey('delivery', $body);
        $this->assertSame('Abşeron', $body['region']);
        $this->assertArrayNotHasKey('region_id', $body);
        $this->assertSame('Nizami küç. 12', $body['address_first']);
        $this->assertSame('AZ1000', $body['zip']);
    }

    public function testUpdateParcelEncodesReferencesInThePath()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(200, array('data' => array('id' => 1, 'status' => 'expected')));

        $this->client($http)->orders()->updateParcel('ORD/1', 'ITEM 2', array('weight_grams' => 1200));

        $call = $http->lastRequest();
        $this->assertSame('PATCH', $call['method']);
        $this->assertSame('https://api.starmile.app/api/v1/orders/ORD%2F1/parcels/ITEM%202', $call['url']);
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

    public function testLabelByOrderIdReturnsRawPdfBytes()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueRaw(200, '%PDF-1.7 fake-bytes', array('Content-Type' => 'application/pdf'));

        $pdf = $this->client($http)->orders()->label('ORD-1001');

        $this->assertSame('%PDF-1.7 fake-bytes', $pdf);

        $call = $http->lastRequest();
        $this->assertSame('GET', $call['method']);
        $this->assertStringContainsString('/api/v1/orders/label?order_id=ORD-1001', $call['url']);
        $this->assertSame('application/pdf', $call['headers']['Accept']);
    }

    public function testLabelByMerchantTrackingIgnoresOrderId()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueRaw(200, '%PDF-1.7', array('Content-Type' => 'application/pdf'));

        $this->client($http)->orders()->label('ORD-1001', 'BARCODE-1');

        $call = $http->lastRequest();
        $this->assertStringContainsString('merchant_tracking=BARCODE-1', $call['url']);
        $this->assertStringNotContainsString('order_id', $call['url']);
    }

    private function client(FakeHttpClient $http)
    {
        return new Client(new Configuration('id', 'secret', array('http_client' => $http, 'max_attempts' => 1)));
    }
}
