<?php

namespace Starmile\PartnerSdk\Exception;

use Exception;

/**
 * Base class for every exception thrown by the SDK. Catching this single type
 * isolates all Starmile failures from the rest of your application.
 */
class StarmileException extends Exception
{
}
