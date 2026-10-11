<?php namespace Bonniss26\Imagery\Tests\Unit;

use Bonniss26\Imagery\Classes\SourceResolver;
use Bonniss26\Imagery\Tests\Fakes\FakeMediaStorage;
use PHPUnit\Framework\TestCase;

class SourceResolverTest extends TestCase
{
    /** @var FakeMediaStorage */
    private $media;

    protected function setUp(): void
    {
        $this->media = new FakeMediaStorage();
        $this->media->put('/photos/a.jpg', 'bytes-a');
    }

    public function testResolvesAMediaPathWithOrWithoutTheLeadingSlash(): void
    {
        $resolver = $this->resolver();

        $this->assertSame('media:/photos/a.jpg', $resolver->resolve('/photos/a.jpg')->key());
        $this->assertSame('media:/photos/a.jpg', $resolver->resolve('photos/a.jpg')->key());
        $this->assertSame('bytes-a', $resolver->resolve('photos/a.jpg')->bytes());
    }

    /** So `{{ photo|media|lqip }}` works as well as `{{ photo|lqip }}`. */
    public function testAcceptsTheOutputOfTheMediaFilter(): void
    {
        $resolver = $this->resolver();

        $this->assertSame('media:/photos/a.jpg', $resolver->resolve('/storage/app/media/photos/a.jpg')->key());
        $this->assertSame('media:/photos/a.jpg', $resolver->resolve('https://example.com/storage/app/media/photos/a.jpg')->key());

        $this->media->put('/photos/with space.jpg', 'x');
        $this->assertSame('media:/photos/with space.jpg', $resolver->resolve('/storage/app/media/photos/with%20space.jpg')->key());
    }

    public function testAMissingImageIsNull(): void
    {
        $this->assertNull($this->resolver()->resolve('/photos/missing.jpg'));
        $this->assertNull($this->resolver()->resolve(null));
        $this->assertNull($this->resolver()->resolve(''));
        $this->assertNull($this->resolver()->resolve(42));
    }

    public function testTraversalNeverReachesTheDisk(): void
    {
        $this->media->put('/../config/app.php', 'secret');

        $this->assertNull($this->resolver()->resolve('/../config/app.php'));
        $this->assertNull($this->resolver()->resolve('photos/../../config/app.php'));
    }

    /**
     * A URL to someone else's image is not ours to fetch: following it would
     * make a template filter an SSRF primitive.
     */
    public function testForeignUrlsAreIgnored(): void
    {
        $this->assertNull($this->resolver()->resolve('https://evil.example/photo.jpg'));
        $this->assertNull($this->resolver()->resolve('//evil.example/photo.jpg'));
        $this->assertNull($this->resolver()->resolve('file:///etc/passwd'));
    }

    public function testAbsolutePathsAreRefusedUnlessEnabled(): void
    {
        $file = $this->tempImage();

        $this->assertNull($this->resolver()->resolve($file));
        $this->assertNull($this->resolver(['allow_absolute_paths' => true])->resolve($file), 'enabled but no allowed root');

        $allowed = $this->resolver(['allow_absolute_paths' => true, 'allowed_roots' => [dirname($file)]]);
        $this->assertSame('path:' . realpath($file), $allowed->resolve($file)->key());
    }

    public function testAnAllowedRootCannotBeEscapedWithDotDot(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'imagery-root-' . uniqid();
        mkdir($root);
        $outside = $this->tempImage();

        try {
            $resolver = $this->resolver(['allow_absolute_paths' => true, 'allowed_roots' => [$root]]);
            $sneaky = $root . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . basename($outside);

            $this->assertNull($resolver->resolve($sneaky));
        } finally {
            rmdir($root);
        }
    }

    public function testResolvesAFileModelAndTheFirstOfACollection(): void
    {
        $file = $this->fileModel(7);

        $this->assertSame('file:7', $this->resolver()->resolve($file)->key());
        $this->assertSame('contents-7', $this->resolver()->resolve($file)->bytes());
        $this->assertSame('123:2026-01-01', $this->resolver()->resolve($file)->identity());

        $this->assertSame('file:7', $this->resolver()->resolve(new \ArrayIterator([$file, $this->fileModel(8)]))->key());
        $this->assertNull($this->resolver()->resolve(new \ArrayIterator([])));
    }

    public function testKeysRoundTrip(): void
    {
        $file = $this->fileModel(7);
        $resolver = new SourceResolver($this->media, function (int $id) use ($file) {
            return $id === 7 ? $file : null;
        });

        $this->assertSame('media:/photos/a.jpg', $resolver->fromKey('media:/photos/a.jpg')->key());
        $this->assertSame('file:7', $resolver->fromKey('file:7')->key());
        $this->assertNull($resolver->fromKey('file:8'));
        $this->assertNull($resolver->fromKey('file:abc'));
        $this->assertNull($resolver->fromKey('path:/etc/passwd'), 'absolute paths stay off unless enabled');
        $this->assertNull($resolver->fromKey('nonsense'));
    }

    public function testPathOnlyIdentitySkipsTheRoundTrip(): void
    {
        $resolver = new SourceResolver($this->media, function () {
            return null;
        }, [], false);

        $this->assertSame('', $resolver->resolve('/photos/a.jpg')->identity());
        $this->assertSame('v1', $this->resolver()->resolve('/photos/a.jpg')->identity());
    }

    private function resolver(array $sources = []): SourceResolver
    {
        return new SourceResolver($this->media, function () {
            return null;
        }, $sources);
    }

    private function fileModel(int $id): object
    {
        return new class ($id) {
            public $id;
            public $disk_name;
            public $file_size = 123;
            public $updated_at = '2026-01-01';

            public function __construct(int $id)
            {
                $this->id = $id;
                $this->disk_name = "disk{$id}.jpg";
            }

            public function getContents(): string
            {
                return "contents-{$this->id}";
            }
        };
    }

    private function tempImage(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, 'not really an image');
        register_shutdown_function(function () use ($path) {
            @unlink($path);
        });

        return $path;
    }
}
