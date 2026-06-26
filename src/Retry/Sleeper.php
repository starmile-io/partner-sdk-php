<?php

namespace Starmile\PartnerSdk\Retry;

/**
 * Pauses between retry attempts. Abstracted so tests can run instantly with a
 * no-op double instead of really sleeping.
 */
interface Sleeper
{
    /**
     * @param int $milliseconds
     * @return void
     */
    public function sleepMs($milliseconds);
}
