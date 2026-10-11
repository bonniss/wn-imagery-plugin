<?php namespace Bonniss26\Imagery\Classes;

/**
 * Caps how many placeholders one request may generate inline.
 *
 * Generation costs roughly 21ms per source megapixel with GD, because decoding
 * the original dominates. A gallery page full of uncached 10MP photos can blow
 * the request timeout on its own. Past the budget the filter returns nothing
 * and the image is left for warming: a missing placeholder is a cosmetic
 * problem, a timed-out request is not.
 *
 * Lives as a singleton for the request, so the count spans every filter call
 * on the page.
 */
final class RequestBudget
{
    /** @var int */
    private $remaining;

    public function __construct(int $limit)
    {
        $this->remaining = max(0, $limit);
    }

    /** Consumes one unit; false once the budget is spent. */
    public function take(): bool
    {
        if ($this->remaining <= 0) {
            return false;
        }

        $this->remaining--;

        return true;
    }

    public function remaining(): int
    {
        return $this->remaining;
    }
}
