<?php

namespace Starmile\PartnerSdk\Enum;

/**
 * The delivery channel of a Service — `home` (region_id), `pudo` (pudo_id) or
 * `locker` (locker_id). It is a property of the chosen Service (its delivery_type),
 * NOT a field the partner sends on an order: pick the Service whose channel you
 * want and provide the matching destination id (region / pudo_id / locker_id).
 * `return` / `clearance` / `cross_docking` services have no channel. Kept as the
 * canonical channel vocabulary for reference.
 */
final class DeliveryMethod
{
    const HOME = 'home';
    const PUDO = 'pudo';
    const LOCKER = 'locker';

    /**
     * @return array<int, string>
     */
    public static function all()
    {
        return array(self::HOME, self::PUDO, self::LOCKER);
    }
}
