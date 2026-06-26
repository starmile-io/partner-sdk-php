<?php

namespace Starmile\PartnerSdk\Resource;

use Generator;
use Starmile\PartnerSdk\Pagination\StatusChangePage;

/**
 * The status pool — a PULL feed of status changes on the partner's orders that
 * replaces outbound webhooks (scope: `status:read`).
 *
 *   GET /api/v1/partner/changes?since={cursor}&limit={n}
 *
 * Poll with the cursor you last processed; you receive every change after it, in
 * order. Persist the returned cursor and pass it back as `since` next time.
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
     * @return StatusChangePage
     */
    public function changes($since = 0, $limit = 100)
    {
        $response = $this->connection->get('/api/v1/partner/changes', array(
            'since' => max(0, (int) $since),
            'limit' => $this->clampLimit($limit),
        ));

        return StatusChangePage::fromResponse($response);
    }

    /**
     * Drain the pool from `$since`, yielding every change row one at a time and
     * paging automatically until nothing more is waiting. The caller should record
     * the highest `cursor` it has handled so it can resume later.
     *
     * @param int $since
     * @param int $limit
     * @return Generator<int, array<string, mixed>>
     */
    public function each($since = 0, $limit = 100)
    {
        $cursor = max(0, (int) $since);

        do {
            $page = $this->changes($cursor, $limit);

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
