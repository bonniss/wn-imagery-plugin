<?php namespace Bonniss26\Imagery\Tests\Unit;

use Bonniss26\Imagery\Classes\CachedPlaceholder;
use Bonniss26\Imagery\Classes\Imagery;
use Bonniss26\Imagery\Classes\OptionResolver;
use Bonniss26\Imagery\Classes\RequestBudget;
use Bonniss26\Imagery\Classes\RetryPolicy;
use Bonniss26\Imagery\Classes\SourceResolver;
use Bonniss26\Imagery\Tests\Fakes\FakeMediaStorage;
use Bonniss26\Imagery\Tests\Fakes\InMemoryStore;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * The caching and generation decisions, end to end through the real library,
 * with the database and the Media Library swapped for in-memory fakes.
 */
class ImageryTest extends TestCase
{
    /** @var FakeMediaStorage */
    private $media;

    /** @var InMemoryStore */
    private $store;

    /** @var DateTimeImmutable */
    private $now;

    protected function setUp(): void
    {
        $this->media = new FakeMediaStorage();
        $this->media->put('/a.png', self::png(0x336699));
        $this->media->put('/broken.jpg', 'not an image');
        $this->store = new InMemoryStore();
        $this->now = new DateTimeImmutable('2026-06-01 12:00:00');
    }

    public function testAMissGeneratesInlineAndAHitDoesNotTouchTheDisk(): void
    {
        $imagery = $this->imagery();

        $this->assertSame('linear-gradient(#336699,#336699)', $imagery->css('/a.png'));
        $this->assertSame(1, $this->media->reads);

        $this->assertSame('linear-gradient(#336699,#336699)', $this->imagery()->css('/a.png'));
        $this->assertSame(1, $this->media->reads, 'a cache hit must not read the image again');
    }

    public function testStyleGivesAWholeDeclaration(): void
    {
        $this->assertSame('background-image:linear-gradient(#336699,#336699)', $this->imagery()->style('/a.png'));
    }

    /**
     * The `lqip` filter promises a background-image value. css_only cannot give
     * one, so it falls back rather than breaking the template; `lqipStyle`
     * renders css_only as its custom property.
     */
    public function testCssOnlyFallsBackForTheCssFilterOnly(): void
    {
        $imagery = $this->imagery(['mode' => 'css_only']);

        $this->assertSame('linear-gradient(#336699,#336699)', $imagery->css('/a.png'));
        $this->assertMatchesRegularExpression('/^--lqip:-?\d+$/', $imagery->style('/a.png'));
    }

    public function testNothingIsGeneratedWhenNotLazy(): void
    {
        $this->assertSame('', $this->imagery([], ['lazy' => false])->css('/a.png'));
        $this->assertSame(0, $this->media->reads);
        $this->assertSame([], $this->store->rows);
    }

    public function testTheRequestBudgetCapsInlineGeneration(): void
    {
        $this->media->put('/b.png', self::png(0x112233));
        $this->media->put('/c.png', self::png(0x445566));
        $imagery = $this->imagery([], ['budget' => 2]);

        $this->assertNotSame('', $imagery->css('/a.png'));
        $this->assertNotSame('', $imagery->css('/b.png'));
        $this->assertSame('', $imagery->css('/c.png'), 'the third miss exceeds the budget');

        // Hits are free: the budget only counts generation.
        $this->assertNotSame('', $imagery->css('/a.png'));
    }

    public function testWithAQueueAMissIsQueuedOnceAndNotGenerated(): void
    {
        $queued = [];
        $imagery = $this->imagery([], ['dispatch' => function (string $key, array $options) use (&$queued) {
            $queued[] = $key;
        }]);

        $this->assertSame('', $imagery->css('/a.png'));
        $this->assertSame('', $imagery->style('/a.png'));
        $this->assertSame(['media:/a.png'], $queued, 'one job per image per request');
        $this->assertSame(0, $this->media->reads);
    }

    public function testAQueuedJobFillsTheCacheTheNextRenderReads(): void
    {
        $captured = null;
        $imagery = $this->imagery([], ['dispatch' => function (string $key, array $options) use (&$captured) {
            $captured = [$key, $options];
        }]);
        $imagery->css('/a.png');

        // What the job does in the worker.
        $this->assertSame('generated', $this->imagery()->warm($captured[0], $captured[1], false, true));

        $this->assertSame('linear-gradient(#336699,#336699)', $imagery->css('/a.png'));
    }

    public function testAFailureIsRecordedAndBacksOff(): void
    {
        $this->assertSame('', $this->imagery()->css('/broken.jpg'));
        $row = $this->onlyRow();
        $this->assertSame(CachedPlaceholder::STATUS_FAILED, $row->status);
        $this->assertSame(1, $row->attempts);

        // Inside the backoff window: not even read.
        $reads = $this->media->reads;
        $this->imagery()->css('/broken.jpg');
        $this->assertSame($reads, $this->media->reads);

        // After it: retried, and the count goes up.
        $this->now = $this->now->modify('+16 minutes');
        $this->imagery()->css('/broken.jpg');
        $this->assertSame(2, $this->onlyRow()->attempts);
    }

    public function testAFixedFileIsRetriedImmediately(): void
    {
        $this->imagery()->css('/broken.jpg');

        // Replaced in place with a good image: the identity changes, so the
        // failure no longer applies.
        $this->media->put('/broken.jpg', self::png(0xFF0000), 'v2');

        $this->assertSame('linear-gradient(#ff0000,#ff0000)', $this->imagery()->css('/broken.jpg'));
    }

    public function testAnImageReplacedInPlaceIsRegenerated(): void
    {
        $this->imagery()->css('/a.png');
        $this->media->put('/a.png', self::png(0x00FF00), 'v2');

        $this->assertSame('linear-gradient(#00ff00,#00ff00)', $this->imagery()->css('/a.png'));
        $this->assertCount(1, $this->store->rows, 'updated in place, not a second row');
    }

    public function testDisabledDoesNothingAtAll(): void
    {
        $this->assertSame('', $this->imagery([], ['enabled' => false])->css('/a.png'));
        $this->assertSame(0, $this->media->reads);
    }

    public function testAnythingUnresolvableIsAnEmptyString(): void
    {
        $imagery = $this->imagery();

        $this->assertSame('', $imagery->css(null));
        $this->assertSame('', $imagery->css('/missing.png'));
        $this->assertSame('', $imagery->css(new \stdClass()));
        $this->assertSame('', $imagery->style(['not', 'an', 'image']));
    }

    public function testWarmSkipsWhatIsCachedUnlessForced(): void
    {
        $this->assertSame('generated', $this->imagery()->warm('/a.png'));
        $this->assertSame('cached', $this->imagery()->warm('/a.png'));
        $this->assertSame('generated', $this->imagery()->warm('/a.png', [], true));
        $this->assertSame('failed', $this->imagery()->warm('/broken.jpg'));
        $this->assertSame('missing', $this->imagery()->warm('/nope.png'));
    }

    /**
     * Changing a setting must take effect on the next render without a purge,
     * and the keys the purge command keeps must be exactly the live ones.
     */
    public function testChangedOptionsMissAndCurrentKeysTrackThem(): void
    {
        $this->imagery(['mode' => 'dominant_color'])->css('/a.png');
        $oldKey = array_keys($this->store->rows)[0];

        $gradient = $this->imagery(['mode' => 'gradient']);
        $this->assertStringStartsWith('radial-gradient', $gradient->css('/a.png'));

        $this->assertNotContains($oldKey, $gradient->currentKeysFor('media:/a.png'));
        $this->assertContains($oldKey, $this->imagery(['mode' => 'dominant_color'])->currentKeysFor('media:/a.png'));

        // css_only also keeps the fallback entry the `lqip` filter reads.
        $this->assertContains($oldKey, $this->imagery(['mode' => 'css_only'])->currentKeysFor('media:/a.png'));
    }

    private function imagery(array $settings = [], array $behaviour = []): Imagery
    {
        $now = $this->now;

        return new Imagery(
            new OptionResolver($settings, ['driver' => 'gd']),
            new SourceResolver($this->media, function () {
                return null;
            }),
            $this->store,
            new RequestBudget($behaviour['budget'] ?? 5),
            new RetryPolicy(5, 15, 10080),
            $behaviour['enabled'] ?? true,
            $behaviour['lazy'] ?? true,
            $behaviour['dispatch'] ?? null,
            function () use ($now) {
                return $now;
            }
        );
    }

    private function onlyRow(): CachedPlaceholder
    {
        $this->assertCount(1, $this->store->rows);

        return array_values($this->store->rows)[0];
    }

    private static function png(int $rgb): string
    {
        $image = imagecreatetruecolor(8, 8);
        imagefilledrectangle($image, 0, 0, 7, 7, $rgb);

        ob_start();
        imagepng($image);

        return ob_get_clean();
    }
}
