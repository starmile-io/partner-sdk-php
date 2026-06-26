<?php

namespace Starmile\PartnerSdk\Pagination;

/**
 * One page of the status pool: the change rows, the cursor to pass as `since` on
 * the next poll, and whether more rows are already waiting beyond this page.
 */
final class StatusChangePage
{
    /** @var array<int, array<string, mixed>> */
    private $changes;

    /** @var int */
    private $nextCursor;

    /** @var bool */
    private $hasMore;

    /**
     * @param array<int, array<string, mixed>> $changes
     */
    public function __construct(array $changes, $nextCursor, $hasMore)
    {
        $this->changes = $changes;
        $this->nextCursor = (int) $nextCursor;
        $this->hasMore = (bool) $hasMore;
    }

    /**
     * @param array<string, mixed> $response
     * @return StatusChangePage
     */
    public static function fromResponse(array $response)
    {
        $changes = isset($response['data']) && is_array($response['data']) ? $response['data'] : array();
        $nextCursor = isset($response['next_cursor']) ? $response['next_cursor'] : 0;
        $hasMore = isset($response['has_more']) ? $response['has_more'] : false;

        return new self($changes, $nextCursor, $hasMore);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function changes()
    {
        return $this->changes;
    }

    /**
     * The cursor to send as `since` to fetch the next page.
     *
     * @return int
     */
    public function nextCursor()
    {
        return $this->nextCursor;
    }

    /**
     * @return bool
     */
    public function hasMore()
    {
        return $this->hasMore;
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return $this->changes === array();
    }
}
