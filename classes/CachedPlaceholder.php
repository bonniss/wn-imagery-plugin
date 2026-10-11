<?php namespace Bonniss26\Imagery\Classes;

use Bonniss26\Lqip\LqipResult;
use DateTimeInterface;

/**
 * One cache row, as read back: either a generated value or a recorded failure.
 */
final class CachedPlaceholder
{
    const STATUS_OK = 'ok';
    const STATUS_FAILED = 'failed';

    /** @var string */
    public $status;

    /** @var string|null LqipResult::KIND_* for an ok row. */
    public $kind;

    /** @var string|null */
    public $value;

    /** @var string|null Custom property name, for a css_var row. */
    public $property;

    /** @var string */
    public $identity;

    /** @var int */
    public $attempts;

    /** @var DateTimeInterface|null When a failed row may be retried; null means never. */
    public $retryAt;

    /** @var int|null The image's displayed size; null on a row written by 1.0. */
    public $width;

    /** @var int|null */
    public $height;

    public function __construct(
        string $status,
        ?string $kind,
        ?string $value,
        ?string $property,
        string $identity,
        int $attempts = 0,
        ?DateTimeInterface $retryAt = null,
        ?int $width = null,
        ?int $height = null
    ) {
        $this->status = $status;
        $this->kind = $kind;
        $this->value = $value;
        $this->property = $property;
        $this->identity = $identity;
        $this->attempts = $attempts;
        $this->retryAt = $retryAt;
        $this->width = $width;
        $this->height = $height;
    }

    public function isOk(): bool
    {
        return $this->status === self::STATUS_OK && $this->value !== null;
    }

    /**
     * An ok row with everything this version stores. A 1.0 row is still served
     * as it is, but imagery:warm regenerates it to fill in the size.
     */
    public function isComplete(): bool
    {
        return $this->isOk() && $this->width !== null && $this->height !== null;
    }

    /**
     * Rebuilds the library's value object. The kind strings are the library's
     * wire format (see LqipResult), which is why they can be stored as-is.
     */
    public function toResult(): ?LqipResult
    {
        if (!$this->isOk()) {
            return null;
        }

        if ($this->kind === LqipResult::KIND_CSS_VAR) {
            $result = LqipResult::cssVar($this->value, (string) $this->property);
        } elseif ($this->kind === LqipResult::KIND_HASH) {
            $result = LqipResult::hash($this->value);
        } else {
            $result = LqipResult::css($this->value);
        }

        return $this->width && $this->height ? $result->withDimensions($this->width, $this->height) : $result;
    }

    /** A failed row still inside its backoff window, or one that gave up for good. */
    public function isBackingOff(DateTimeInterface $now): bool
    {
        return $this->status === self::STATUS_FAILED
            && ($this->retryAt === null || $this->retryAt > $now);
    }
}
