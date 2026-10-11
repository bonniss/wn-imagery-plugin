<?php namespace Bonniss26\Imagery\Classes;

use Bonniss26\Lqip\LqipResult;
use DateTimeInterface;

/**
 * Where generated placeholders are persisted. DatabaseStore is the real one;
 * the interface exists so the caching decisions in Imagery can be tested
 * without a database.
 *
 * Implementations must not throw: a cache that cannot be written is a
 * performance problem, never a reason for a page to fail.
 */
interface PlaceholderStore
{
    public function find(string $cacheKey): ?CachedPlaceholder;

    public function saveResult(string $cacheKey, string $sourceKey, string $identity, string $mode, LqipResult $result): void;

    public function saveFailure(string $cacheKey, string $sourceKey, string $identity, string $mode, int $attempts, ?DateTimeInterface $retryAt): void;
}
