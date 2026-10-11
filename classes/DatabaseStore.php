<?php namespace Bonniss26\Imagery\Classes;

use Bonniss26\Imagery\Models\Placeholder;
use Bonniss26\Lqip\LqipResult;
use DateTimeInterface;
use Throwable;

/**
 * PlaceholderStore backed by the bonniss26_imagery_placeholders table.
 *
 * Writes swallow every failure. The likeliest one is two requests generating
 * the same image at once and colliding on the unique key - harmless, both hold
 * the same value - but a read-only replica or a full disk must not fail a page
 * render either.
 */
class DatabaseStore implements PlaceholderStore
{
    public function find(string $cacheKey): ?CachedPlaceholder
    {
        try {
            $row = Placeholder::where('cache_key', $cacheKey)->first();
        } catch (Throwable $e) {
            return null;
        }

        if (!$row) {
            return null;
        }

        return new CachedPlaceholder(
            (string) $row->status,
            $row->kind,
            $row->value,
            $row->property,
            (string) $row->identity,
            (int) $row->attempts,
            $row->retry_at
        );
    }

    public function saveResult(string $cacheKey, string $sourceKey, string $identity, string $mode, LqipResult $result): void
    {
        $this->save($cacheKey, [
            'source' => $sourceKey,
            'identity' => $identity,
            'mode' => $mode,
            'status' => CachedPlaceholder::STATUS_OK,
            'kind' => $result->kind(),
            'value' => $result->value(),
            'property' => $result->cssProperty(),
            'attempts' => 0,
            'retry_at' => null,
        ]);
    }

    public function saveFailure(string $cacheKey, string $sourceKey, string $identity, string $mode, int $attempts, ?DateTimeInterface $retryAt): void
    {
        $this->save($cacheKey, [
            'source' => $sourceKey,
            'identity' => $identity,
            'mode' => $mode,
            'status' => CachedPlaceholder::STATUS_FAILED,
            'kind' => null,
            'value' => null,
            'property' => null,
            'attempts' => $attempts,
            'retry_at' => $retryAt,
        ]);
    }

    private function save(string $cacheKey, array $attributes): void
    {
        try {
            $row = Placeholder::firstOrNew(['cache_key' => $cacheKey]);
            foreach ($attributes as $name => $value) {
                $row->{$name} = $value;
            }
            $row->save();
        } catch (Throwable $e) {
            // See the class docblock.
        }
    }
}
