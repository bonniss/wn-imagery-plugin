<?php namespace Bonniss26\Imagery\Tests\Unit;

use Bonniss26\Imagery\Classes\CacheKey;
use Bonniss26\Imagery\Classes\Defaults;
use PHPUnit\Framework\TestCase;

class CacheKeyTest extends TestCase
{
    public function testInertOptionsDoNotSplitTheCache(): void
    {
        $base = ['mode' => 'dominant_color'] + Defaults::generation();

        $this->assertSame(
            CacheKey::make('media:/a.jpg', $base, 'webp'),
            CacheKey::make('media:/a.jpg', ['quality' => 90, 'long_edge' => 8, 'driver' => 'gd'] + $base, 'jpg'),
            'quality, long_edge, driver and format mean nothing to dominant_color'
        );
    }

    public function testSignificantOptionsDo(): void
    {
        $blur = ['mode' => 'blur'] + Defaults::generation();

        $this->assertNotSame(
            CacheKey::make('media:/a.jpg', $blur, 'webp'),
            CacheKey::make('media:/a.jpg', ['quality' => 90] + $blur, 'webp')
        );
        $this->assertNotSame(
            CacheKey::make('media:/a.jpg', $blur, 'webp'),
            CacheKey::make('media:/b.jpg', $blur, 'webp')
        );
    }

    /**
     * Two servers sharing one database, one without WebP: they ask for the same
     * `format` but produce different bytes, so they must not share an entry.
     */
    public function testBlurIsKeyedOnTheFormatActuallyProduced(): void
    {
        $blur = ['mode' => 'blur', 'format' => 'webp'] + Defaults::generation();

        $this->assertNotSame(
            CacheKey::make('media:/a.jpg', $blur, 'webp'),
            CacheKey::make('media:/a.jpg', $blur, 'jpg')
        );
    }

    public function testOptionOrderDoesNotMatter(): void
    {
        $options = ['mode' => 'blur'] + Defaults::generation();

        $this->assertSame(
            CacheKey::make('media:/a.jpg', $options, 'webp'),
            CacheKey::make('media:/a.jpg', array_reverse($options, true), 'webp')
        );
    }

    public function testIsAFixedLengthHexString(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', CacheKey::make('x', [], null));
    }
}
