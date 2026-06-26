<?php

namespace Starmile\PartnerSdk\Resource;

use Starmile\PartnerSdk\Connection;

/**
 * Shared base for every API resource group. Holds the authenticated
 * {@see Connection} the concrete resources issue requests through.
 */
abstract class AbstractResource
{
    /** @var Connection */
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }
}
