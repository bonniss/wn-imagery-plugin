<?php namespace Bonniss26\Imagery\Tests\Unit;

use Bonniss26\Imagery\Classes\Defaults;
use Bonniss26\Imagery\Classes\OptionResolver;
use Bonniss26\Lqip\PlaceholderGenerator;
use PHPUnit\Framework\TestCase;

class OptionResolverTest extends TestCase
{
    public function testWithNothingSetEverythingComesFromTheLibrary(): void
    {
        $this->assertSame(Defaults::generation(), (new OptionResolver([], []))->resolve());
    }

    public function testSettingsBeatConfigByDefault(): void
    {
        $resolver = new OptionResolver(['mode' => 'blur'], ['mode' => 'gradient']);

        $this->assertSame('blur', $resolver->resolve()['mode']);
    }

    public function testConfigBeatsSettingsWhenConfiguredAsCode(): void
    {
        $resolver = new OptionResolver(['mode' => 'blur'], ['mode' => 'gradient'], false);

        $this->assertSame('gradient', $resolver->resolve()['mode']);
    }

    public function testPerCallOverridesBeatEverything(): void
    {
        $resolver = new OptionResolver(['mode' => 'blur'], ['mode' => 'gradient']);

        $this->assertSame('css_only', $resolver->resolve(['mode' => 'css_only'])['mode']);
    }

    /**
     * An editor blanking a field means "inherit", not "pass an empty string to
     * the generator". Winter's settings model returns such a field as null or
     * "" depending on how it was cleared; both must fall through.
     */
    public function testBlankValuesFallThroughToTheNextLayer(): void
    {
        $resolver = new OptionResolver(
            ['mode' => '', 'quality' => null, 'format' => ''],
            ['mode' => 'blur', 'quality' => 60]
        );

        $options = $resolver->resolve();

        $this->assertSame('blur', $options['mode']);
        $this->assertSame(60, $options['quality']);
        $this->assertSame(PlaceholderGenerator::DEFAULT_OPTIONS['format'], $options['format']);
    }

    /**
     * One bad value - a mode removed with the plugin that registered it, an
     * engine uninstalled from the server - must degrade to the next layer, not
     * switch every placeholder off.
     */
    public function testInvalidValuesAreSkippedRatherThanPassedOn(): void
    {
        $resolver = new OptionResolver(
            ['mode' => 'no_such_mode', 'driver' => 'vips', 'format' => 'png', 'quality' => 'high'],
            ['mode' => 'gradient']
        );

        $options = $resolver->resolve();

        $this->assertSame('gradient', $options['mode']);
        $this->assertSame('auto', $options['driver']);
        $this->assertSame(PlaceholderGenerator::DEFAULT_OPTIONS['format'], $options['format']);
        $this->assertSame(PlaceholderGenerator::DEFAULT_OPTIONS['quality'], $options['quality']);
    }

    public function testValuesAreCastToWhatTheLibraryExpects(): void
    {
        $options = (new OptionResolver(['long_edge' => '24', 'quality' => '40', 'blur' => '0'], []))->resolve();

        $this->assertSame(24, $options['long_edge']);
        $this->assertSame(40, $options['quality']);
        $this->assertFalse($options['blur']);
    }

    public function testOnlyGenerationKeysComeOut(): void
    {
        $options = (new OptionResolver([], []))->resolve(['enabled' => false, 'request_budget' => 9]);

        $this->assertSame(Defaults::GENERATION_KEYS, array_keys($options));
    }

    public function testSmoothIsABooleanLikeBlur(): void
    {
        $this->assertTrue((new OptionResolver(['smooth' => '1'], []))->resolve()['smooth']);
        $this->assertFalse((new OptionResolver([], []))->resolve()['smooth'], 'off by default');
    }

    /**
     * Normalised, so the spellings of one colour share a cache key; dropped
     * when not a colour, because the library would answer an invalid one with
     * no placeholder at all.
     */
    public function testBackgroundIsNormalisedOrDropped(): void
    {
        foreach (['#FFF', 'fff', '#ffffff', ' FFFFFF '] as $spelling) {
            $this->assertSame('#ffffff', (new OptionResolver(['background' => $spelling], []))->resolve()['background'], $spelling);
        }

        $options = (new OptionResolver(['background' => 'white'], ['background' => '#000']))->resolve();
        $this->assertSame('#000000', $options['background'], 'an invalid setting falls through to the config');

        $this->assertNull((new OptionResolver([], []))->resolve()['background'], 'transparency kept by default');
    }
}
