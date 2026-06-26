<?php

namespace Starmile\PartnerSdk\Builder;

use InvalidArgumentException;
use Starmile\PartnerSdk\Enum\EventType;

/**
 * Fluent builder for an inbound Partner API event. Validates the event type and
 * its `data` fields against the known server contract ({@see EventType}) before a
 * request is ever sent, so unknown fields fail fast and locally.
 */
final class EventBuilder
{
    /** @var string */
    private $type;

    /** @var string */
    private $trackingNumber;

    /** @var string */
    private $eventId;

    /** @var string|null */
    private $occurredAt;

    /** @var array<string, mixed> */
    private $data = array();

    /**
     * @param string $type           One of {@see EventType}.
     * @param string $trackingNumber The Starmile tracking number of the package.
     * @param string $eventId        The partner's own idempotency key.
     */
    public function __construct($type, $trackingNumber, $eventId)
    {
        if (!EventType::isValid($type)) {
            throw new InvalidArgumentException(
                'Unknown event type "' . $type . '". Use a constant from ' . EventType::class . '.'
            );
        }

        $this->type = (string) $type;
        $this->trackingNumber = (string) $trackingNumber;
        $this->eventId = (string) $eventId;
    }

    /**
     * @return EventBuilder
     */
    public static function make($type, $trackingNumber, $eventId)
    {
        return new self($type, $trackingNumber, $eventId);
    }

    /**
     * The ISO-8601 timestamp the event occurred (optional).
     *
     * @return $this
     */
    public function occurredAt($timestamp)
    {
        $this->occurredAt = (string) $timestamp;

        return $this;
    }

    /**
     * Set one `data` field. Rejects any field the event type does not define.
     *
     * @return $this
     */
    public function set($field, $value)
    {
        $allowed = EventType::dataFieldsFor($this->type);

        if (!in_array($field, $allowed, true)) {
            throw new InvalidArgumentException(
                'Event "' . $this->type . '" does not accept the data field "' . $field . '". '
                . 'Allowed: ' . implode(', ', $allowed) . '.'
            );
        }

        $this->data[$field] = $value;

        return $this;
    }

    /**
     * The universal free-text note every event accepts.
     *
     * @return $this
     */
    public function note($note)
    {
        return $this->set('note', $note);
    }

    /**
     * Merge several `data` fields at once. Each is validated via {@see set()}.
     *
     * @param array<string, mixed> $data
     * @return $this
     */
    public function data(array $data)
    {
        foreach ($data as $field => $value) {
            $this->set($field, $value);
        }

        return $this;
    }

    /**
     * The request payload.
     *
     * @return array<string, mixed>
     */
    public function toArray()
    {
        $payload = array(
            'event_id' => $this->eventId,
            'type' => $this->type,
            'tracking_number' => $this->trackingNumber,
        );

        if ($this->occurredAt !== null) {
            $payload['occurred_at'] = $this->occurredAt;
        }

        if ($this->data !== array()) {
            $payload['data'] = $this->data;
        }

        return $payload;
    }
}
