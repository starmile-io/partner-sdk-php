<?php

namespace Starmile\PartnerSdk\Retry;

/**
 * The default {@see Sleeper} — really pauses the process via usleep().
 */
final class RealSleeper implements Sleeper
{
    public function sleepMs($milliseconds)
    {
        $milliseconds = (int) $milliseconds;

        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }
}
