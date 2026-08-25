<?php

namespace Starmile\PartnerSdk\Resource\V2;

use Starmile\PartnerSdk\Builder\EventBuilder;
use Starmile\PartnerSdk\Resource\AbstractResource;

/**
 * Inbound lifecycle events on /api/v2 (scopes: `events:*` / `leg:handoff`).
 * Same contract as v1 — only the path is versioned.
 */
final class Events extends AbstractResource
{
    /**
     * Report an event. Accepts an {@see EventBuilder} or a raw payload array.
     *
     * @param EventBuilder|array<string, mixed> $event
     * @return array<string, mixed>
     */
    public function report($event)
    {
        $payload = $event instanceof EventBuilder ? $event->toArray() : $event;

        return $this->connection->post('/api/v2/partner/events', $payload);
    }

    /**
     * Convenience wrapper around {@see report()} for a one-line call.
     *
     * @param string               $type
     * @param string               $trackingNumber
     * @param string               $eventId
     * @param array<string, mixed> $data
     * @param string|null          $occurredAt
     * @return array<string, mixed>
     */
    public function reportEvent($type, $trackingNumber, $eventId, array $data = array(), $occurredAt = null)
    {
        $builder = EventBuilder::make($type, $trackingNumber, $eventId)->data($data);

        if ($occurredAt !== null) {
            $builder->occurredAt($occurredAt);
        }

        return $this->report($builder);
    }
}
