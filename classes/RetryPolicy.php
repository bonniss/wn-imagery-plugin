<?php namespace Bonniss26\Imagery\Classes;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * When a failed placeholder may be tried again.
 *
 * A failure is recorded as a row rather than as an absence, so a broken image
 * backs off instead of being decoded again on every single render. The wait
 * doubles from `base_minutes` up to `max_minutes`; after `max_attempts` the
 * entry is never retried until `imagery:purge --failed` clears it.
 */
final class RetryPolicy
{
    /** @var int */
    private $maxAttempts;

    /** @var int */
    private $baseMinutes;

    /** @var int */
    private $maxMinutes;

    public function __construct(int $maxAttempts = 5, int $baseMinutes = 15, int $maxMinutes = 10080)
    {
        $this->maxAttempts = max(1, $maxAttempts);
        $this->baseMinutes = max(1, $baseMinutes);
        $this->maxMinutes = max($this->baseMinutes, $maxMinutes);
    }

    public static function fromConfig(array $config): self
    {
        return new self(
            (int) ($config['max_attempts'] ?? 5),
            (int) ($config['base_minutes'] ?? 15),
            (int) ($config['max_minutes'] ?? 10080)
        );
    }

    /**
     * @param int $attempts Failures so far, including the one just recorded.
     * @return DateTimeImmutable|null null once attempts are exhausted: never retry.
     */
    public function retryAt(int $attempts, DateTimeInterface $now): ?DateTimeImmutable
    {
        if ($attempts >= $this->maxAttempts) {
            return null;
        }

        // 2^(n-1) without overflowing: the cap is reached long before the exponent matters.
        $minutes = $this->baseMinutes;
        for ($i = 1; $i < $attempts && $minutes < $this->maxMinutes; $i++) {
            $minutes *= 2;
        }
        $minutes = min($minutes, $this->maxMinutes);

        return DateTimeImmutable::createFromFormat('U', (string) $now->getTimestamp())
            ->modify("+{$minutes} minutes");
    }
}
