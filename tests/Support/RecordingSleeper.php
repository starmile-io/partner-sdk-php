<?php

namespace Starmile\PartnerSdk\Tests\Support;

use Starmile\PartnerSdk\Retry\Sleeper;

/**
 * A {@see Sleeper} test double — records the requested delays instead of really
 * sleeping, so retry tests run instantly.
 */
final class RecordingSleeper implements Sleeper
{
    /** @var array<int, int> */
    public $sleeps = array();

    public function sleepMs($milliseconds)
    {
        $this->sleeps[] = (int) $milliseconds;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->sleeps);
    }
}
