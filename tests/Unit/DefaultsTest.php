<?php namespace Bonniss26\Imagery\Tests\Unit;

use Bonniss26\Imagery\Classes\Defaults;
use Bonniss26\Lqip\Drivers\Driver;
use Bonniss26\Lqip\Drivers\Image;
use Bonniss26\Lqip\PlaceholderGenerator;
use Bonniss26\Lqip\Strategies\LqipStrategy;
use Bonniss26\Lqip\LqipResult;
use PHPUnit\Framework\TestCase;

class DefaultsTest extends TestCase
{
    /**
     * The drift test. If the library adds or renames a generation option, this
     * fails here rather than silently producing placeholders under an option
     * this plugin never passes through and never keys its cache on.
     */
    public function testGenerationKeysMatchTheLibrarysOwnOptions(): void
    {
        $this->assertSame(
            [],
            array_diff(Defaults::GENERATION_KEYS, array_keys(PlaceholderGenerator::DEFAULT_OPTIONS)),
            'GENERATION_KEYS names an option the library does not have'
        );

        $libraryKeys = array_keys(PlaceholderGenerator::DEFAULT_OPTIONS);
        $this->assertSame(
            [],
            array_diff($libraryKeys, Defaults::GENERATION_KEYS),
            'the library gained an option this plugin does not pass through or key on'
        );
    }

    public function testGenerationDefaultsComeFromTheLibrary(): void
    {
        $defaults = Defaults::generation();

        foreach ($defaults as $key => $value) {
            $this->assertSame(PlaceholderGenerator::DEFAULT_OPTIONS[$key], $value, $key);
        }
    }

    public function testOnlyBlurTreatsTheNumericOptionsAsSignificant(): void
    {
        $this->assertSame(['mode'], Defaults::significantKeys('dominant_color'));
        $this->assertSame(['mode'], Defaults::significantKeys('gradient'));
        $this->assertSame(['mode'], Defaults::significantKeys('css_only'));

        $this->assertSame(
            ['mode', 'long_edge', 'quality', 'blur', 'format'],
            Defaults::significantKeys('blur'),
            'blur is the only mode the numeric knobs reach'
        );
    }

    /**
     * GD and Imagick give equally valid placeholders for one image, so choosing
     * an engine for speed must not discard the cache. The encode format that
     * really differs by engine is keyed separately, by CacheKey.
     */
    public function testTheDriverIsNeverSignificant(): void
    {
        foreach (['dominant_color', 'gradient', 'css_only', 'blur'] as $mode) {
            $this->assertNotContains('driver', Defaults::significantKeys($mode), $mode);
        }
    }

    public function testDriverChoicesAreOnlyThoseInstalledHere(): void
    {
        $drivers = Defaults::drivers();

        $this->assertSame('auto', $drivers[0]);
        $this->assertSame(PlaceholderGenerator::availableDrivers(), array_slice($drivers, 1));
    }

    /**
     * Over-keying an unknown mode only wastes cache entries; under-keying it
     * would serve one custom strategy's output for another's request.
     */
    public function testAnUnknownModeIsKeyedOnEveryOption(): void
    {
        $this->assertSame(Defaults::GENERATION_KEYS, Defaults::significantKeys('some_custom_mode'));
    }

    public function testCssModesExcludeCssOnly(): void
    {
        $this->assertTrue(Defaults::isCssMode('dominant_color'));
        $this->assertTrue(Defaults::isCssMode('gradient'));
        $this->assertTrue(Defaults::isCssMode('blur'));

        // The whole reason the plugin needs a guard: this one produces a custom
        // property value, which is not valid inside background-image.
        $this->assertFalse(Defaults::isCssMode('css_only'));
        $this->assertFalse(Defaults::isCssMode('some_custom_mode'));
    }

    public function testTheCssFallbackModeIsItselfACssMode(): void
    {
        $this->assertTrue(Defaults::isCssMode(Defaults::FALLBACK_CSS_MODE));
    }

    public function testModesIncludeEveryBuiltInMode(): void
    {
        $modes = Defaults::modes();

        foreach (['dominant_color', 'gradient', 'blur', 'css_only'] as $mode) {
            $this->assertContains($mode, $modes);
        }
    }

    public function testModesIncludeAStrategyRegisteredByAThirdParty(): void
    {
        $mode = 'imagery_test_custom_mode';

        PlaceholderGenerator::extend($mode, new class implements LqipStrategy {
            public function generate(Image $image, Driver $driver, array $options): ?LqipResult
            {
                return LqipResult::css('linear-gradient(#000,#000)');
            }
        });

        $this->assertContains(
            $mode,
            Defaults::modes(),
            'a custom mode must be selectable without editing this plugin'
        );
    }
}
