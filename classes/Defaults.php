<?php namespace Bonniss26\Imagery\Classes;

use Bonniss26\Lqip\PlaceholderGenerator;

/**
 * The one place that knows what a default is.
 *
 * Winter offers two obvious mechanisms for this and neither works. A settings
 * model's initSettingsData() runs only while no settings record exists, so it
 * is dead code the moment an editor saves the form once. A `default:` in
 * fields.yaml applies only when the record does not exist, so a field added in
 * a later release renders blank forever on an existing install. Both are
 * first-render-only, which means a value added or changed after launch silently
 * diverges from whatever the code believes.
 *
 * So defaults are resolved at runtime instead, every time, by coalescing nulls
 * across settings -> config file -> library. This class supplies the last of
 * those and the vocabulary the other two are validated against; OptionResolver
 * does the coalescing.
 */
final class Defaults
{
    /**
     * Option keys that reach the generator. Anything outside this list is
     * plugin-level behaviour and must never leak into a generation call or a
     * cache key.
     */
    const GENERATION_KEYS = ['mode', 'long_edge', 'quality', 'blur', 'format'];

    /**
     * Which options actually change the output, per mode.
     *
     * Only `blur` reads the numeric knobs; for the other three, long_edge and
     * quality are inert. Including them in a cache key regardless would split
     * the cache into entries that are byte-identical, so an admin nudging
     * quality would needlessly discard every dominant_color placeholder too.
     *
     * A mode registered through PlaceholderGenerator::extend() is unknown here
     * and falls back to every key - the safe direction, since over-keying only
     * costs cache entries while under-keying serves wrong values.
     */
    const SIGNIFICANT_KEYS = [
        'dominant_color' => ['mode'],
        'gradient' => ['mode'],
        'css_only' => ['mode'],
        'blur' => ['mode', 'long_edge', 'quality', 'blur', 'format'],
    ];

    /** Modes whose value can be dropped into a CSS `background-image` list. */
    const CSS_MODES = ['dominant_color', 'gradient', 'blur'];

    /** Used when a configured mode cannot produce a CSS value. Cheap and always valid. */
    const FALLBACK_CSS_MODE = 'dominant_color';

    /**
     * The library's own defaults, narrowed to the keys we pass through.
     *
     * @return array<string,mixed>
     */
    public static function generation(): array
    {
        $defaults = [];
        foreach (self::GENERATION_KEYS as $key) {
            if (array_key_exists($key, PlaceholderGenerator::DEFAULT_OPTIONS)) {
                $defaults[$key] = PlaceholderGenerator::DEFAULT_OPTIONS[$key];
            }
        }

        return $defaults;
    }

    /**
     * Keys that affect the output for a given mode.
     *
     * @return string[]
     */
    public static function significantKeys(string $mode): array
    {
        return self::SIGNIFICANT_KEYS[$mode] ?? self::GENERATION_KEYS;
    }

    public static function isCssMode(string $mode): bool
    {
        return in_array($mode, self::CSS_MODES, true);
    }

    /**
     * Mode names offered in the settings dropdown, including any registered by
     * a third party through PlaceholderGenerator::extend(), so a custom mode is
     * selectable without editing this plugin.
     *
     * @return string[]
     */
    public static function modes(): array
    {
        $builtIn = array_keys(self::SIGNIFICANT_KEYS);
        $extended = array_keys(self::registeredStrategies());

        return array_values(array_unique(array_merge($builtIn, $extended)));
    }

    /** @return string[] */
    public static function formats(): array
    {
        return ['webp', 'jpg'];
    }

    /**
     * Whether GD can actually encode WebP.
     *
     * Part of the cache key for the blur mode, not merely a capability probe:
     * without WebP the library silently produces JPEG at roughly nine times the
     * size. Two application servers sharing one database, one of them built
     * without WebP, would otherwise agree on a cache key while disagreeing
     * about what it holds.
     */
    public static function hasWebp(): bool
    {
        return function_exists('imagewebp');
    }

    /**
     * @return array<string,mixed>
     */
    private static function registeredStrategies(): array
    {
        // Reflection rather than a public accessor: the library exposes
        // extend() but nothing to read the registry back. Failing soft here
        // just means custom modes are absent from the dropdown, which is far
        // better than a settings page that cannot render.
        try {
            $property = new \ReflectionProperty(PlaceholderGenerator::class, 'customStrategies');
            $property->setAccessible(true);

            return (array) $property->getValue();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
