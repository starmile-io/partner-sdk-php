<?php

namespace Starmile\PartnerSdk\Resource\V2;

use Starmile\PartnerSdk\Builder\ManifestBuilder;
use Starmile\PartnerSdk\Resource\AbstractResource;

/**
 * Inbound air manifests on /api/v2 (scope: `manifests:write`).
 *
 * A manifest says which packages travel on which air waybill, in which bag.
 * Until it arrives the consignment cannot be filed with Azerbaijani customs at
 * all — the customs manifest is filed PER AIR WAYBILL, and nothing else records
 * which waybill a package is on.
 *
 * File it as soon as the consignment is built, and file it again whenever it
 * changes. Each filing REPLACES the previous one for that waybill (mode `full`,
 * the default), which is what lets a package pulled from the flight actually
 * disappear from the count instead of being declared to customs anyway.
 */
final class Manifests extends AbstractResource
{
    /**
     * File a manifest. Accepts a {@see ManifestBuilder} or a raw payload array.
     *
     * @param ManifestBuilder|array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    public function file($manifest)
    {
        $payload = $manifest instanceof ManifestBuilder ? $manifest->toArray() : $manifest;

        return $this->unwrap($this->connection->post('/api/v2/manifests', $payload));
    }

    /**
     * Validate a manifest and get the full report WITHOUT writing anything —
     * the right way to make a first call against a new integration.
     *
     * @param ManifestBuilder|array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    public function dryRun($manifest)
    {
        $payload = $manifest instanceof ManifestBuilder ? $manifest->toArray() : $manifest;
        $payload['dry_run'] = true;

        return $this->unwrap($this->connection->post('/api/v2/manifests', $payload));
    }

    /**
     * The import report is wrapped under a `data` key, as every single-resource
     * Partner API response is.
     *
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    private function unwrap(array $response)
    {
        return isset($response['data']) && is_array($response['data']) ? $response['data'] : $response;
    }
}
