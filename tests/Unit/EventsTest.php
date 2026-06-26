<?php

namespace Starmile\PartnerSdk\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Starmile\PartnerSdk\Builder\EventBuilder;
use Starmile\PartnerSdk\Client;
use Starmile\PartnerSdk\Configuration;
use Starmile\PartnerSdk\Enum\EventType;
use Starmile\PartnerSdk\Enum\Scope;
use Starmile\PartnerSdk\Exception\ValidationException;
use Starmile\PartnerSdk\Tests\Support\FakeHttpClient;

final class EventsTest extends TestCase
{
    public function testReportEventSendsTheContract()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(200, array('result' => 'applied', 'order_status' => 'delivered'));

        $outcome = $this->client($http)->events()->reportEvent(
            EventType::SHIPMENT_DELIVERED,
            'SM-TRACK-1',
            'evt-1',
            array('recipient_name' => 'Jane', 'note' => 'left at door'),
            '2026-06-27T10:00:00Z'
        );

        $this->assertSame('applied', $outcome['result']);

        $body = $http->lastJsonBody();
        $this->assertSame('shipment.delivered', $body['type']);
        $this->assertSame('SM-TRACK-1', $body['tracking_number']);
        $this->assertSame('evt-1', $body['event_id']);
        $this->assertSame('2026-06-27T10:00:00Z', $body['occurred_at']);
        $this->assertSame('Jane', $body['data']['recipient_name']);
    }

    public function testUnknownEventTypeIsRejectedLocally()
    {
        $this->expectException(InvalidArgumentException::class);
        EventBuilder::make('shipment.teleported', 'SM-1', 'evt-1');
    }

    public function testUnknownDataFieldIsRejectedLocally()
    {
        $this->expectException(InvalidArgumentException::class);
        EventBuilder::make(EventType::PARCEL_TIMEOUT, 'SM-1', 'evt-1')->set('reason', 'nope');
    }

    public function testRejectedEventSurfacesErrorAndHint()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(422, array(
            'result' => 'rejected',
            'error' => 'shipment.delivered is not a legal next step from received_at_hub.',
            'hint' => 'Report shipment.out_for_delivery first.',
        ));

        try {
            $this->client($http)->events()->reportEvent(EventType::SHIPMENT_DELIVERED, 'SM-1', 'evt-1');
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame('Report shipment.out_for_delivery first.', $e->getHint());
            $this->assertStringContainsString('not a legal next step', $e->getMessage());
        }
    }

    public function testScopeMappingMatchesTheFamily()
    {
        $this->assertSame(Scope::EVENTS_TRANSPORT, EventType::scopeFor(EventType::SHIPMENT_DELIVERED));
        $this->assertSame(Scope::EVENTS_PUDO, EventType::scopeFor(EventType::PARCEL_RECEIVED));
        $this->assertSame(Scope::EVENTS_CUSTOMS, EventType::scopeFor(EventType::CUSTOMS_HELD));
        $this->assertSame(Scope::LEG_HANDOFF, EventType::scopeFor(EventType::LEG_RECEIVED));
    }

    public function testNoteIsAcceptedForEveryEvent()
    {
        $payload = EventBuilder::make(EventType::PARCEL_TIMEOUT, 'SM-1', 'evt-1')->note('timed out')->toArray();
        $this->assertSame('timed out', $payload['data']['note']);
    }

    private function client(FakeHttpClient $http)
    {
        return new Client(new Configuration('id', 'secret', array('http_client' => $http)));
    }
}
