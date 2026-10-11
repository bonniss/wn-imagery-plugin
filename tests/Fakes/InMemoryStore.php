<?php namespace Bonniss26\Imagery\Tests\Fakes;

use Bonniss26\Imagery\Classes\CachedPlaceholder;
use Bonniss26\Imagery\Classes\PlaceholderStore;
use Bonniss26\Lqip\LqipResult;
use DateTimeInterface;

final class InMemoryStore implements PlaceholderStore
{
    /** @var array<string,CachedPlaceholder> */
    public $rows = [];

    /** @var array<string,string> cache key => source key */
    public $sources = [];

    public function find(string $cacheKey): ?CachedPlaceholder
    {
        return $this->rows[$cacheKey] ?? null;
    }

    public function saveResult(string $cacheKey, string $sourceKey, string $identity, string $mode, LqipResult $result): void
    {
        $this->rows[$cacheKey] = new CachedPlaceholder(
            CachedPlaceholder::STATUS_OK,
            $result->kind(),
            $result->value(),
            $result->cssProperty(),
            $identity
        );
        $this->sources[$cacheKey] = $sourceKey;
    }

    public function saveFailure(string $cacheKey, string $sourceKey, string $identity, string $mode, int $attempts, ?DateTimeInterface $retryAt): void
    {
        $this->rows[$cacheKey] = new CachedPlaceholder(
            CachedPlaceholder::STATUS_FAILED,
            null,
            null,
            null,
            $identity,
            $attempts,
            $retryAt
        );
        $this->sources[$cacheKey] = $sourceKey;
    }
}
