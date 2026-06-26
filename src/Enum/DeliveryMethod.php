<?php

namespace Starmile\PartnerSdk\Enum;

/**
 * The delivery destination method an order may target. The method is bound to the
 * Service's type — Home Delivery → `home` (region_id), Pudo Delivery → `pudo`
 * (pudo_id), Locker → `locker` (locker_id). Clearance / Cross-Docking services
 * take no destination. `delivery` is optional on intake; when sent it is validated
 * against the Service's type.
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
