<?php

namespace Starmile\PartnerSdk\Resource\V2;

use Starmile\PartnerSdk\Resource\AbstractResource;

/**
 * Read-only catalogue on /api/v2 (scope: `catalogue:read`). Same shapes as v1.
 */
final class Catalogue extends AbstractResource
{
    /**
     * The Services the partner is contracted for.
     *
     * @return array<int, array<string, mixed>>
     */
    public function services()
    {
        $response = $this->connection->get('/api/v2/services');

        return isset($response['data']) ? $response['data'] : array();
    }

    /**
     * The Rates bound to the credential's partner.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rates()
    {
        $response = $this->connection->get('/api/v2/rates');

        return isset($response['data']) ? $response['data'] : array();
    }
}
