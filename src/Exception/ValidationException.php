<?php

namespace Starmile\PartnerSdk\Exception;

/**
 * HTTP 422 — the request was well-formed but rejected by validation. The
 * field-level errors are exposed via {@see errors()}, keyed by field name with a
 * list of human-readable messages (Laravel's standard validation shape).
 *
 * Inbound-event rejections also use 422; for those the explanation lives in the
 * base `error`/`hint` instead of the `errors` map.
 */
class ValidationException extends ApiException
{
    /**
     * The field-level validation errors: `{ "field": ["message", ...], ... }`.
     *
     * @return array<string, array<int, string>>
     */
    public function errors()
    {
        if (isset($this->body['errors']) && is_array($this->body['errors'])) {
            return $this->body['errors'];
        }

        return array();
    }

    /**
     * The flat list of every validation message across all fields.
     *
     * @return array<int, string>
     */
    public function allMessages()
    {
        $messages = array();

        foreach ($this->errors() as $fieldMessages) {
            foreach ((array) $fieldMessages as $message) {
                $messages[] = $message;
            }
        }

        return $messages;
    }
}
