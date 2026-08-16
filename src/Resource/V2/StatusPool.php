<?php

namespace Starmile\PartnerSdk\Resource\V2;

use Generator;
use Starmile\PartnerSdk\Pagination\StatusChangePage;
use Starmile\PartnerSdk\Resource\AbstractResource;

/**
 * The v2 status pool (scope: `status:read`).
 *
 *   GET /api/v2/partner/changes?since={cursor}&limit={n}&tracking_number={tn}&external_parent_id={ref}
 *
 * Same polling model as v1 — page with the cursor you last processed — with
 * two versioned differences you must not mix across versions:
 *
 *   - The CURSOR is a new id space (the timeline entry id). A cursor stored
 *     from v1 means nothing here: when you migrate, start from `since = 0`
 *     (or from a filtered drain) and dedupe on (tracking_number, status).
 *   - Each row names your own order reference `order_id` (v1: external_parent_id)
 *     and the sub-order reference `sub_order_id` (v1: external_id).
 *
 * The status VOCABULARY is identical to v1, including the customs strings.
 */
final class StatusPool extends AbstractResource
{
    /** The largest page the server will return in one poll. */
    const MAX_LIMIT = 200;

    /**
     * Fetch a single page of changes after `$since`.
     *
     * @param int $since Cursor of the last change you processed (0 to start).
     * @param int $limit Page size (1–200).
     * @param string|null $trackingNumber Narrow to one Starmile tracking number; null for the whole feed.
     * @param string|null $externalParentId Narrow to one of your own order references; null to not filter.
     * @return StatusChangePage
     */
    public function changes($since = 0, $limit = 100, $trackingNumber = null, $externalParentId = null)
    {
        $query = array(
            'since' => max(0, (int) $since),
            'limit' => $this->clampLimit($limit),
        );

        if ($trackingNumber !== null && $trackingNumber !== '') {
            $query['tracking_number'] = (string) $trackingNumber;
        }

        if ($externalParentId !== null && $externalParentId !== '') {
            $query['external_parent_id'] = (string) $externalParentId;
        }

        $response = $this->connection->get('/api/v2/partner/changes', $query);

        return StatusChangePage::fromResponse($response);
    }

    /**
     * Drain the pool from `$since`, yielding every change row and paging
     * automatically until nothing more is waiting.
     *
     * @param int $since
     * @param int $limit
     * @param string|null $trackingNumber
     * @param string|null $externalParentId
     * @return Generator<int, array<string, mixed>>
     */
    public function each($since = 0, $limit = 100, $trackingNumber = null, $externalParentId = null)
    {
        $cursor = max(0, (int) $since);

        do {
            $page = $this->changes($cursor, $limit, $trackingNumber, $externalParentId);

            foreach ($page->changes() as $change) {
                yield $change;
            }

            $cursor = $page->nextCursor();
        } while ($page->hasMore());
    }

    /**
     * @return int
     */
    private function clampLimit($limit)
    {
        $limit = (int) $limit;

        if ($limit < 1) {
            return 1;
        }

        return $limit > self::MAX_LIMIT ? self::MAX_LIMIT : $limit;
    }
}
