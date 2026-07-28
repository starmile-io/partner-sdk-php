<?php

namespace Starmile\PartnerSdk\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Starmile\PartnerSdk\Client;
use Starmile\PartnerSdk\Configuration;
use Starmile\PartnerSdk\Enum\Reason;
use Starmile\PartnerSdk\Tests\Support\FakeHttpClient;

final class StatusPoolTest extends TestCase
{
    public function testChangesReturnsAPageWithCursorAndHasMore()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(200, array(
            'data' => array(array('cursor' => 10, 'tracking_number' => 'SM1', 'status' => 'delivered')),
            'next_cursor' => 10,
            'has_more' => true,
        ));

        $page = $this->client($http)->statusPool()->changes(0, 50);

        $this->assertCount(1, $page->changes());
        $this->assertSame(10, $page->nextCursor());
        $this->assertTrue($page->hasMore());

        // The poll passes since + a clamped limit.
        $this->assertStringContainsString('since=0', $http->requests[1]['url']);
        $this->assertStringContainsString('limit=50', $http->requests[1]['url']);
    }

    public function testChangeRowExposesParcelScopedExternalIdAndCountry()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(200, array(
            'data' => array(array(
                'cursor' => 5,
                'tracking_number' => 'SM9',
                'external_parent_id' => 'PO-1',
                'external_id' => 'ITEM-1',
                'country' => 'CN',
                'status' => 'received_at_hub',
                'previous_status' => 'waiting_for_arrival',
                'occurred_at' => '2026-07-06 00:00:00',
                'timezone' => 'UTC',
            )),
            'next_cursor' => 5,
            'has_more' => false,
        ));

        $changes = $this->client($http)->statusPool()->changes(0)->changes();

        // A parcel-scoped ("sub-order") change carries the partner's per-parcel
        // reference alongside the order reference, the ISO-2 country the change
        // occurred in, and the plain occurred_at + its timezone — all passed
        // through verbatim.
        $this->assertSame('PO-1', $changes[0]['external_parent_id']);
        $this->assertSame('ITEM-1', $changes[0]['external_id']);
        $this->assertSame('CN', $changes[0]['country']);
        $this->assertSame('2026-07-06 00:00:00', $changes[0]['occurred_at']);
        $this->assertSame('UTC', $changes[0]['timezone']);
    }

    public function testChangesForwardsTheTrackingNumberFilter()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(200, array('data' => array(), 'next_cursor' => 0, 'has_more' => false));

        $this->client($http)->statusPool()->changes(0, 100, 'STM001');

        // The tracking number narrows the feed to one order/parcel server-side.
        $this->assertStringContainsString('tracking_number=STM001', $http->requests[1]['url']);
    }

    public function testChangesOmitsTheTrackingNumberWhenNotGiven()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(200, array('data' => array(), 'next_cursor' => 0, 'has_more' => false));

        $this->client($http)->statusPool()->changes(0, 100);

        // No filter → the param is absent, so the whole feed is polled as before.
        $this->assertStringNotContainsString('tracking_number', $http->requests[1]['url']);
        $this->assertStringNotContainsString('external_parent_id', $http->requests[1]['url']);
    }

    public function testChangesForwardsTheExternalParentIdFilter()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(200, array('data' => array(), 'next_cursor' => 0, 'has_more' => false));

        // Track by the partner's own reference, no tracking number held.
        $this->client($http)->statusPool()->changes(0, 100, null, 'PO-1001');

        $this->assertStringContainsString('external_parent_id=PO-1001', $http->requests[1]['url']);
        $this->assertStringNotContainsString('tracking_number', $http->requests[1]['url']);
    }

    public function testEachDrainsAcrossPagesUntilExhausted()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(200, array(
            'data' => array(array('cursor' => 1), array('cursor' => 2)),
            'next_cursor' => 2,
            'has_more' => true,
        ));
        $http->queueJson(200, array(
            'data' => array(array('cursor' => 3)),
            'next_cursor' => 3,
            'has_more' => false,
        ));

        $cursors = array();
        foreach ($this->client($http)->statusPool()->each(0) as $change) {
            $cursors[] = $change['cursor'];
        }

        $this->assertSame(array(1, 2, 3), $cursors);
        // Token + two pages.
        $this->assertCount(3, $http->requests);
        $this->assertStringContainsString('since=2', $http->requests[2]['url']);
    }

    public function testLimitIsClampedToTheServerMaximum()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(200, array('data' => array(), 'next_cursor' => 0, 'has_more' => false));

        $this->client($http)->statusPool()->changes(0, 9999);

        $this->assertStringContainsString('limit=200', $http->requests[1]['url']);
    }

    public function testChangeRowExposesTheReasonPair()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(200, array(
            'data' => array(
                array(
                    'cursor' => 11,
                    'tracking_number' => 'SM1',
                    'status' => 'customs_hold',
                    'previous_status' => 'customs_in_progress',
                    'reason' => 'missing_declaration',
                    'reason_detail' => null,
                ),
                array(
                    'cursor' => 12,
                    'tracking_number' => 'SM2',
                    'status' => 'delivery_failed',
                    'previous_status' => 'out_for_delivery',
                    'reason' => 'customer_absent',
                    'reason_detail' => 'no answer at the gate',
                ),
                array(
                    'cursor' => 13,
                    'tracking_number' => 'SM3',
                    'status' => 'received_at_hub',
                    'previous_status' => null,
                    'reason' => null,
                    'reason_detail' => null,
                ),
            ),
            'next_cursor' => 13,
            'has_more' => false,
        ));

        $changes = $this->client($http)->statusPool()->changes(0)->changes();

        $this->assertSame(Reason::MISSING_DECLARATION, $changes[0]['reason']);
        $this->assertNull($changes[0]['reason_detail']);

        // A coded reason and a human's note can arrive together.
        $this->assertSame(Reason::CUSTOMER_ABSENT, $changes[1]['reason']);
        $this->assertSame('no answer at the gate', $changes[1]['reason_detail']);

        // Most changes have no why at all — the fields are present but null, so
        // an integration must never assume a reason is there.
        $this->assertNull($changes[2]['reason']);
        $this->assertNull($changes[2]['reason_detail']);
    }

    public function testAnUnrecognisedReasonCodeIsPassedThroughRatherThanRejected()
    {
        // The catalogue grows over time. A code this SDK version has never heard
        // of must still reach the caller intact, so an integration can fall back
        // to reason_detail instead of breaking on deploy day.
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(200, array(
            'data' => array(array(
                'cursor' => 20,
                'tracking_number' => 'SM4',
                'status' => 'delivery_failed',
                'reason' => 'some_future_reason',
                'reason_detail' => 'a situation this SDK predates',
            )),
            'next_cursor' => 20,
            'has_more' => false,
        ));

        $changes = $this->client($http)->statusPool()->changes(0)->changes();

        $this->assertSame('some_future_reason', $changes[0]['reason']);
        $this->assertSame('a situation this SDK predates', $changes[0]['reason_detail']);
    }

    private function client(FakeHttpClient $http)
    {
        return new Client(new Configuration('id', 'secret', array('http_client' => $http, 'max_attempts' => 1)));
    }
}
