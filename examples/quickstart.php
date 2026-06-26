<?php

/**
 * Starmile Partner SDK — quickstart.
 *
 * Run after `composer install`:
 *   STARMILE_CLIENT_ID=... STARMILE_CLIENT_SECRET=... php examples/quickstart.php
 *
 * Credentials and the base URL come from the environment — never hardcode them.
 */

require __DIR__ . '/../vendor/autoload.php';

use Starmile\PartnerSdk\Builder\OrderBuilder;
use Starmile\PartnerSdk\Builder\ProductBuilder;
use Starmile\PartnerSdk\Builder\ShipmentBuilder;
use Starmile\PartnerSdk\Client;
use Starmile\PartnerSdk\Enum\EventType;
use Starmile\PartnerSdk\Exception\StarmileException;
use Starmile\PartnerSdk\Exception\ValidationException;

$client = Client::create(
    getenv('STARMILE_CLIENT_ID'),
    getenv('STARMILE_CLIENT_SECRET'),
    array(
        // Defaults to https://api.starmile.app; point at your sandbox otherwise.
        'base_url' => getenv('STARMILE_BASE_URL') ?: null,
    )
);

try {
    // 1. Discover the services you may order against.
    $services = $client->catalogue()->services();
    $serviceId = $services[0]['id'];
    echo 'Using service #' . $serviceId . ' (' . $services[0]['name'] . ')' . PHP_EOL;

    // 2. Create an order.
    $order = OrderBuilder::make($serviceId, 'ORD-1001')
        ->recipient('Jane Doe', '+994500000000', 'jane@example.com')
        ->deliverToPudo(42)
        ->shippingCost(9.90)
        ->addShipment(
            ShipmentBuilder::make('ITEM-1')
                ->merchantTracking('BARCODE-1')
                ->weightGrams(1200)
                ->addProduct(
                    ProductBuilder::make('Running shoes')
                        ->hsCode('640299')
                        ->declaredValue(59.99, 'USD')
                        ->quantity(1)
                )
        );

    $created = $client->orders()->create($order);
    echo 'Created order, tracking number: ' . $created['tracking_number'] . PHP_EOL;

    // 3. Poll the status pool for changes since your last cursor.
    $cursor = 0; // load your persisted cursor here
    foreach ($client->statusPool()->each($cursor) as $change) {
        echo sprintf('  [%d] %s -> %s%s', $change['cursor'], $change['tracking_number'], $change['status'], PHP_EOL);
        $cursor = $change['cursor']; // persist this
    }

    // 4. Report an inbound event (if your credential holds an event scope).
    $outcome = $client->events()->reportEvent(
        EventType::SHIPMENT_OUT_FOR_DELIVERY,
        $created['tracking_number'],
        'evt-0001',
        array('driver' => 'Driver A', 'eta' => '2026-06-28T09:00:00Z')
    );
    echo 'Event result: ' . $outcome['result'] . PHP_EOL;
} catch (ValidationException $e) {
    echo 'Validation failed: ' . $e->getMessage() . PHP_EOL;
    foreach ($e->allMessages() as $message) {
        echo '  - ' . $message . PHP_EOL;
    }
} catch (StarmileException $e) {
    echo 'Request failed: ' . $e->getMessage() . PHP_EOL;
}
