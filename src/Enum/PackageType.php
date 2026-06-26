<?php

namespace Starmile\PartnerSdk\Enum;

/**
 * The optional handling type a shipment may declare. Records-only — it does not
 * change routing, but flags special handling at the hub.
 */
final class PackageType
{
    const FRAGILE = 'fragile';
    const BREAKABLE = 'breakable';
    const LIQUID = 'liquid';

    /**
     * @return array<int, string>
     */
    public static function all()
    {
        return array(self::FRAGILE, self::BREAKABLE, self::LIQUID);
    }
}
