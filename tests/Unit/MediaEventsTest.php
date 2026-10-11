<?php namespace Bonniss26\Imagery\Tests\Unit;

use Bonniss26\Imagery\Classes\CachedPlaceholder;
use Bonniss26\Imagery\Classes\Imagery;
use Bonniss26\Imagery\Classes\MediaEvents;
use Bonniss26\Imagery\Classes\OptionResolver;
use Bonniss26\Imagery\Classes\PlaceholderStore;
use Bonniss26\Imagery\Classes\RequestBudget;
use Bonniss26\Imagery\Classes\RetryPolicy;
use Bonniss26\Imagery\Classes\SourceResolver;
use Bonniss26\Imagery\Tests\Fakes\FakeMediaStorage;
use Bonniss26\Imagery\Tests\Fakes\InMemoryStore;
use Bonniss26\Lqip\LqipResult;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;

/**
 * What happens to the cache when an editor uploads, renames, moves or
 * deletes an image - the decisions behind Plugin::followMediaChanges().
 */
class MediaEventsTest extends TestCase
{
    /** @var FakeMediaStorage */
    private $media;

    /** @var InMemoryStore */
    private $store;

    protected function setUp(): void
    {
        $this->media = new FakeMediaStorage();
        $this->store = new InMemoryStore();
    }

    /**
     * Winter passes the path on the storage disk, with the library's folder in
     * front; the cache is keyed by the library path.
     */
    public function testAnUploadIsGeneratedRightAway(): void
    {
        $this->media->put('/photos/a.png', self::png());

        $this->events()->uploaded('media/photos/a.png');

        $this->assertSame(['media:/photos/a.png'], array_values($this->store->sources));
        $this->assertTrue($this->onlyRow()->isComplete());
    }

    public function testWithARealQueueTheUploadIsQueuedInstead(): void
    {
        $this->media->put('/a.png', self::png());
        $queued = [];
        $dispatch = function (string $sourceKey, array $options) use (&$queued) {
            $queued[] = [$sourceKey, $options['mode']];
        };

        $this->events([], $dispatch)->uploaded('media/a.png');

        $this->assertSame([['media:/a.png', 'dominant_color']], $queued);
        $this->assertSame([], $this->store->rows, 'nothing generated in the upload request');
    }

    /** A non-CSS mode also needs the fallback the filters read. */
    public function testEveryVariantTemplatesReadIsPrepared(): void
    {
        $this->media->put('/a.png', self::png());

        $this->events(['mode' => 'thumbhash'])->uploaded('media/a.png');

        $kinds = array_map(function (CachedPlaceholder $row) {
            return $row->kind;
        }, array_values($this->store->rows));
        sort($kinds);
        $this->assertSame([LqipResult::KIND_CSS, LqipResult::KIND_HASH], $kinds);
    }

    public function testRenameAndMoveFollowTheFile(): void
    {
        $this->media->put('/a.png', self::png());
        $this->events()->uploaded('media/a.png');

        unset($this->media->files['/a.png']);
        $this->media->put('/b.png', self::png());
        $this->events()->renamed('/a.png', '/b.png');
        $this->assertSame(['media:/b.png'], array_values($this->store->sources));

        unset($this->media->files['/b.png']);
        $this->media->put('/archive/b.png', self::png());
        $this->events()->moved('/b.png', '/archive');
        $this->assertSame(['media:/archive/b.png'], array_values($this->store->sources));
    }

    public function testADeleteForgetsTheImage(): void
    {
        $this->media->put('/a.png', self::png());
        $this->events()->uploaded('media/a.png');

        $this->events()->deleted('/a.png');

        $this->assertSame([], $this->store->rows);
    }

    public function testAnAttachedImageIsPreparedAndAnythingElseIsSkipped(): void
    {
        $this->events()->fileCreated(self::file(7, self::png(), true));
        $this->events()->fileCreated(self::file(8, '%PDF-1.4', false));

        $this->assertSame(['file:7'], array_values($this->store->sources));

        $this->events()->fileDeleted(self::file(7, '', true));
        $this->assertSame([], $this->store->rows);
    }

    /** These run inside the editor's upload request, which they must never break. */
    public function testNothingEscapes(): void
    {
        $this->media->put('/a.png', self::png());
        $broken = new class implements PlaceholderStore {
            public function find(string $cacheKey): ?CachedPlaceholder
            {
                throw new \RuntimeException('database down');
            }

            public function saveResult(string $cacheKey, string $sourceKey, string $identity, string $mode, LqipResult $result): void
            {
                throw new \RuntimeException('database down');
            }

            public function saveFailure(string $cacheKey, string $sourceKey, string $identity, string $mode, int $attempts, ?DateTimeInterface $retryAt): void
            {
                throw new \RuntimeException('database down');
            }

            public function forgetSource(string $sourceKey): void
            {
                throw new \RuntimeException('database down');
            }
        };
        $events = new MediaEvents($this->imagery([], null, $broken), 'media/');

        $events->uploaded('media/a.png');
        $events->renamed('/a.png', '/b.png');
        $events->deleted('/a.png');
        $events->fileCreated(new \stdClass());
        $events->fileDeleted(new \stdClass());

        $this->addToAssertionCount(1);
    }

    public function testDisabledPreparesNothing(): void
    {
        $this->media->put('/a.png', self::png());

        $this->assertSame('disabled', $this->imagery([], null, null, false)->prepare('media:/a.png', true));
        $this->assertSame([], $this->store->rows);
    }

    private function events(array $settings = [], ?\Closure $dispatch = null): MediaEvents
    {
        return new MediaEvents($this->imagery($settings, $dispatch), 'media/');
    }

    private function imagery(array $settings = [], ?\Closure $dispatch = null, ?PlaceholderStore $store = null, bool $enabled = true): Imagery
    {
        $files = [
            7 => self::file(7, self::png(), true),
        ];

        return new Imagery(
            new OptionResolver($settings, ['driver' => 'gd']),
            new SourceResolver($this->media, function (int $id) use ($files) {
                return $files[$id] ?? null;
            }),
            $store ?? $this->store,
            // A zero budget and lazy off: what prepare() does must not depend
            // on either, since it runs outside any page render.
            new RequestBudget(0),
            new RetryPolicy(5, 15, 10080),
            $enabled,
            false,
            $dispatch
        );
    }

    private function onlyRow(): CachedPlaceholder
    {
        $this->assertCount(1, $this->store->rows);

        return array_values($this->store->rows)[0];
    }

    /** Duck-typed like System\Models\File, which SourceResolver accepts. */
    private static function file(int $id, string $contents, bool $isImage): object
    {
        return new class($id, $contents, $isImage) {
            public $id;
            public $disk_name = 'abc.png';
            public $file_size = 10;
            public $updated_at = '2026-10-12 10:00:00';
            private $contents;
            private $image;

            public function __construct(int $id, string $contents, bool $image)
            {
                $this->id = $id;
                $this->contents = $contents;
                $this->image = $image;
            }

            public function getContents(): string
            {
                return $this->contents;
            }

            public function isImage(): bool
            {
                return $this->image;
            }
        };
    }

    private static function png(): string
    {
        $image = imagecreatetruecolor(12, 8);
        imagefilledrectangle($image, 0, 0, 11, 7, 0x336699);

        ob_start();
        imagepng($image);

        return ob_get_clean();
    }
}
