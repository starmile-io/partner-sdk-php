<?php

namespace Starmile\PartnerSdk\Resource;

use Generator;
use Starmile\PartnerSdk\Pagination\StatusChangePage;

/**
 * The status pool — a PULL feed of status changes on the partner's orders that
 * replaces outbound webhooks (scope: `status:read`).
 *
 *   GET /api/v1/partner/changes?since={cursor}&limit={n}&tracking_number={tn}
 *
 * Poll with the cursor you last processed; you receive every change after it, in
 * order. Persist the returned cursor and pass it back as `since` next time.
 *
 * Pass a `$trackingNumber` to narrow the feed to a single Starmile tracking
 * number — an order (order-level changes) or one parcel (parcel-scoped changes).
 * Page from `since = 0` with the tracking number until `hasMore()` is false to
 * reconstruct that subject's full history.
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
     * @return StatusChangePage
     */
    public function changes($since = 0, $limit = 100, $trackingNumber = null)
    {
        $query = array(
            'since' => max(0, (int) $since),
            'limit' => $this->clampLimit($limit),
        );

        if ($trackingNumber !== null && $trackingNumber !== '') {
            $query['tracking_number'] = (string) $trackingNumber;
        }

        $response = $this->connection->get('/api/v1/partner/changes', $query);

        return StatusChangePage::fromResponse($response);
    }

    /**
     * Drain the pool from `$since`, yielding every change row one at a time and
     * paging automatically until nothing more is waiting. The caller should record
     * the highest `cursor` it has handled so it can resume later.
     *
     * @param int $since
     * @param int $limit
     * @param string|null $trackingNumber Narrow to one Starmile tracking number; null for the whole feed.
     * @return Generator<int, array<string, mixed>>
     */
    public function each($since = 0, $limit = 100, $trackingNumber = null)
    {
        $cursor = max(0, (int) $since);

        do {
            $page = $this->changes($cursor, $limit, $trackingNumber);

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
