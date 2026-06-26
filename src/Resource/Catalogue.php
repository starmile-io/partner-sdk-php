<?php

namespace Starmile\PartnerSdk\Resource;

/**
 * The read-only Partner API catalogue (scope: `catalogue:read`). Lists the
 * Services the credential's country runs — whose ids are the valid `service_id`s
 * for creating an order — and the Rates bound to the partner, so pricing can be
 * previewed.
 *
 *   GET /api/v1/services
 *   GET /api/v1/rates
 */
final class Catalogue extends AbstractResource
{
    /**
     * The Services available to the credential, each with its underlying flow.
     * The returned `id` is what you pass as `service_id` on order creation.
     *
     * @return array<int, array<string, mixed>>
     */
    public function services()
    {
        $response = $this->connection->get('/api/v1/services');

        return isset($response['data']) ? $response['data'] : array();
    }

    /**
     * The Rates bound to the credential's partner (with weight tiers), to preview
     * the pricing orders will be billed at.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rates()
    {
        $response = $this->connection->get('/api/v1/rates');

        return isset($response['data']) ? $response['data'] : array();
    }
}
