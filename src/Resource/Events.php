<?php

namespace Starmile\PartnerSdk\Resource;

use Starmile\PartnerSdk\Builder\EventBuilder;

/**
 * Report inbound lifecycle events for packages a partner handles — as a carrier
 * (`shipment.*`), PUDO point (`parcel.*`), customs broker (`customs.*`), or a
 * foreign organization on the next leg (`leg.*`).
 *
 *   POST /api/v1/partner/events   (scope: one of events:transport / events:pudo /
 *                                  events:customs / leg:handoff — per event family)
 *
 * Each accepted event advances the package; an event that is not a legal next step
 * for the package's current status is rejected (422) with an `error` (what is
 * wrong) and a `hint` (how to fix), surfaced on the thrown
 * {@see \Starmile\PartnerSdk\Exception\ValidationException}.
 */
final class Events extends AbstractResource
{
    /**
     * Report an event. Accepts an {@see EventBuilder} or a raw payload array
     * (`event_id`, `type`, `tracking_number`, optional `occurred_at`, `data`).
     *
     * @param EventBuilder|array<string, mixed> $event
     * @return array<string, mixed> The outcome: `result`, `order_status`, and on
     *                              acceptance any echoed fields.
     */
    public function report($event)
    {
        $payload = $event instanceof EventBuilder ? $event->toArray() : $event;

        return $this->connection->post('/api/v1/partner/events', $payload);
    }

    /**
     * Convenience wrapper around {@see report()} for a one-line call.
     *
     * @param string                $type           One of {@see \Starmile\PartnerSdk\Enum\EventType}.
     * @param string                $trackingNumber The Starmile tracking number of the package.
     * @param string                $eventId        The partner's own idempotency key.
     * @param array<string, mixed>  $data           Event-specific fields (validated against the type).
     * @param string|null           $occurredAt     ISO-8601 timestamp the event happened (optional).
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
