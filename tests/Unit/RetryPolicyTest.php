<?php namespace Bonniss26\Imagery\Tests\Unit;

use Bonniss26\Imagery\Classes\RetryPolicy;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class RetryPolicyTest extends TestCase
{
    public function testTheWaitDoublesUpToTheCap(): void
    {
        $now = new DateTimeImmutable('2026-01-01 00:00:00');
        $policy = new RetryPolicy(10, 15, 100);

        $minutes = [];
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $minutes[] = ($policy->retryAt($attempt, $now)->getTimestamp() - $now->getTimestamp()) / 60;
        }

        $this->assertSame([15, 30, 60, 100, 100], $minutes);
    }

    public function testGivesUpAfterMaxAttempts(): void
    {
        $now = new DateTimeImmutable();
        $policy = new RetryPolicy(3, 15, 10080);

        $this->assertNotNull($policy->retryAt(2, $now));
        $this->assertNull($policy->retryAt(3, $now), 'null means never retry');
        $this->assertNull($policy->retryAt(4, $now));
    }

    public function testAHugeAttemptCountDoesNotOverflow(): void
    {
        $now = new DateTimeImmutable('2026-01-01 00:00:00');
        $at = (new RetryPolicy(PHP_INT_MAX, 15, 60))->retryAt(500, $now);

        $this->assertSame(3600, $at->getTimestamp() - $now->getTimestamp());
    }

    public function testFromConfigUsesTheConfigBlock(): void
    {
        $policy = RetryPolicy::fromConfig(['max_attempts' => 1, 'base_minutes' => 1, 'max_minutes' => 1]);

        $this->assertNull($policy->retryAt(1, new DateTimeImmutable()));
    }
}
