<?php

namespace Starmile\PartnerSdk\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Starmile\PartnerSdk\Builder\ManifestBuilder;
use Starmile\PartnerSdk\Client;
use Starmile\PartnerSdk\Configuration;
use Starmile\PartnerSdk\Enum\Scope;
use Starmile\PartnerSdk\Tests\Support\FakeHttpClient;

/**
 * Filing an inbound air manifest — POST /api/v2/manifests.
 *
 * The behaviour worth pinning is that the builder cannot produce a document that
 * disagrees with itself: the package and bag counts are DERIVED from the rows,
 * so a partner who adds a row and forgets to bump a total never files a manifest
 * whose header contradicts its body.
 */
final class ManifestsTest extends TestCase
{
    public function testFileSendsTheDocumentToTheV2PathAndUnwrapsTheReport()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(202, array('data' => array(
            'awb' => '784-85403894',
            'mode' => 'full',
            'rows' => 2,
            'matched' => 2,
            'not_found' => array(),
            'orders_updated' => 2,
        )));

        $report = $this->client($http)->v2()->manifests()->file(
            ManifestBuilder::make('784-85403894')
                ->flight('CZ5795')
                ->origin('cn', 'Corssborder Air Service Limited')
                ->etd('2026-10-15 03:10')
                ->eta('2026-10-16 20:40')
                ->manifestDate('2026-10-12')
                ->grossWeightKg(193)
                ->row('CMX0209075611', 'BOX109022AZ', 1.345)
                ->row('CMX0209075612', 'BOX109022AZ', 2.5)
        );

        $this->assertSame('784-85403894', $report['awb']);
        $this->assertSame(2, $report['orders_updated']);

        $last = $http->lastRequest();
        $this->assertStringContainsString('/api/v2/manifests', $last['url']);

        $body = $http->lastJsonBody();
        $this->assertSame('784-85403894', $body['awb']);
        $this->assertSame('CZ5795', $body['flight']);
        $this->assertSame('CN', $body['origin_country']);
        $this->assertSame('2026-10-15 03:10', $body['etd']);
        $this->assertSame('CMX0209075611', $body['rows'][0]['tracking_number']);
        $this->assertSame('BOX109022AZ', $body['rows'][0]['bag_no']);
    }

    public function testTotalsAreDerivedFromTheRowsSoTheHeaderCannotContradictThem()
    {
        $body = ManifestBuilder::make('784-85403894')
            ->grossWeightKg(193)
            ->row('CMX1', 'BAG-A', 1.0)
            ->row('CMX2', 'BAG-A', 2.0)
            ->row('CMX3', 'BAG-B', 3.0)
            ->toArray();

        $this->assertSame(3, $body['totals']['shipments']);
        // Two distinct bags across three rows — counted, not summed.
        $this->assertSame(2, $body['totals']['bags']);
        // The gross weight is the one figure the rows cannot produce, so it is
        // passed through exactly as stated (it includes bag tare).
        $this->assertSame(193, $body['totals']['weight_kg']);
    }

    public function testAFilingIsTheWholeDocumentUnlessExplicitlyMarkedAsAPart()
    {
        $whole = ManifestBuilder::make('784-85403894')->row('CMX1')->toArray();
        $part = ManifestBuilder::make('784-85403894')->row('CMX1')->asPart()->toArray();

        // `full` is what lets a re-filing REMOVE a package pulled from the flight.
        $this->assertSame('full', $whole['mode']);
        $this->assertSame('part', $part['mode']);
    }

    public function testRowDetailsRideAlongsideTheFieldsTheImportActsOn()
    {
        $body = ManifestBuilder::make('784-85403894')
            ->row('CMX1', 'BAG-A', 1.345, array(
                'recipient_name' => 'Aysel Mammadova',
                'hs_codes' => array('6110200090', '6202409000'),
                'service_method' => 'HOME_DELIVERY',
            ))
            ->toArray();

        $this->assertSame('Aysel Mammadova', $body['rows'][0]['recipient_name']);
        $this->assertSame(array('6110200090', '6202409000'), $body['rows'][0]['hs_codes']);
        $this->assertSame(1.345, $body['rows'][0]['weight_kg']);
    }

    public function testDryRunAsksForTheReportWithoutWriting()
    {
        $http = new FakeHttpClient();
        $http->queueJson(200, array('access_token' => 'tok', 'expires_in' => 3600));
        $http->queueJson(202, array('data' => array('dry_run' => true, 'orders_updated' => 0)));

        $report = $this->client($http)->v2()->manifests()->dryRun(
            ManifestBuilder::make('784-85403894')->row('CMX1', 'BAG-A', 1.0)
        );

        $this->assertTrue($report['dry_run']);
        $this->assertTrue($http->lastJsonBody()['dry_run']);
    }

    public function testAManifestWithoutAWaybillOrARowWithoutATrackingNumberIsRefusedLocally()
    {
        $this->expectException(InvalidArgumentException::class);

        ManifestBuilder::make('   ');
    }

    public function testARowWithoutATrackingNumberIsRefusedLocally()
    {
        $this->expectException(InvalidArgumentException::class);

        ManifestBuilder::make('784-85403894')->row('');
    }

    public function testTheScopeCatalogueCarriesTheManifestGrant()
    {
        $this->assertSame('manifests:write', Scope::MANIFESTS_WRITE);
        $this->assertContains(Scope::MANIFESTS_WRITE, Scope::all());
    }

    private function client(FakeHttpClient $http)
    {
        return new Client(new Configuration('id', 'secret', array('http_client' => $http, 'max_attempts' => 1)));
    }
}
