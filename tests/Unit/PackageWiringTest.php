<?php namespace Bonniss26\Imagery\Tests\Unit;

use Bonniss26\Lqip\PlaceholderGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Proves the standalone test environment is real before anything relies on it.
 *
 * Almost all of this plugin's logic is written to run without a Winter
 * application so it can be tested here. That only works if the core library is
 * genuinely reachable from this repo alone - if it is not, every later test
 * would fail for a reason that has nothing to do with what it is testing.
 */
class PackageWiringTest extends TestCase
{
    public function testCoreLibraryIsAvailable(): void
    {
        $this->assertTrue(
            class_exists(PlaceholderGenerator::class),
            'bonniss26/lqip is not autoloadable; run composer install'
        );
    }

    public function testCoreLibraryIsRecentEnoughToBeSafeToCall(): void
    {
        // These bounds are what stop an out-of-range option from raising an
        // uncatchable E_ERROR inside GD. A core without them would take the whole
        // process down on a bad settings value, so the plugin's own guarantees
        // depend on this being present.
        $this->assertTrue(defined(PlaceholderGenerator::class . '::MAX_QUALITY'));
        $this->assertTrue(defined(PlaceholderGenerator::class . '::MAX_LONG_EDGE'));
    }

    public function testCoreLibraryHasTheApiThisPluginCallsBeyondGeneration(): void
    {
        // The plugin lists modes and drivers for the settings form and asks the
        // driver about WebP for its cache key. A core older than the driver
        // abstraction has none of these.
        $this->assertTrue(method_exists(PlaceholderGenerator::class, 'modes'));
        $this->assertTrue(method_exists(PlaceholderGenerator::class, 'availableDrivers'));
        $this->assertTrue(method_exists(PlaceholderGenerator::class, 'resolveDriver'));
    }

    public function testAnImageEngineIsPresentWithTheFormatsTheDefaultsAssume(): void
    {
        $this->assertNotEmpty(
            PlaceholderGenerator::availableDrivers(),
            'neither ext-gd nor ext-imagick is loaded; every placeholder would be empty'
        );

        // Not a hard requirement, but without it the blur mode silently falls back
        // to JPEG at roughly 9x the size - so a suite that passes without WebP is
        // exercising a different code path than the defaults describe.
        if (!PlaceholderGenerator::resolveDriver('auto')->supportsFormat('webp')) {
            $this->markTestIncomplete('the image engine lacks WebP; blur-mode sizes will not match the documented defaults');
        }

        $this->assertTrue(true);
    }
}
