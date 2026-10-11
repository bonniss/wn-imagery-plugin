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
    const GENERATION_KEYS = ['mode', 'long_edge', 'quality', 'blur', 'format', 'driver'];

    /**
     * Which options actually change the output, per mode.
     *
     * Only `blur` reads the numeric knobs; for the other three, long_edge and
     * quality are inert. Including them in a cache key regardless would split
     * the cache into entries that are byte-identical, so an admin nudging
     * quality would needlessly discard every dominant_color placeholder too.
     *
     * `driver` is in none of them. GD and Imagick resample slightly differently,
     * but both values are equally valid placeholders for the same image, so
     * switching engine for speed should not throw the cache away. What *does*
     * differ by engine is whether WebP is available; CacheKey keys on the format
     * actually produced instead.
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

    /** Accepted values for the `driver` option. */
    const DRIVERS = ['auto', 'gd', 'imagick'];

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
        return PlaceholderGenerator::modes();
    }

    /** @return string[] */
    public static function formats(): array
    {
        return ['webp', 'jpg'];
    }

    /**
     * Driver choices that would actually work in this process: `auto`, plus
     * each engine that is loaded. Offering one that is not installed would let
     * an editor pick a setting that silently turns every placeholder off.
     *
     * @return string[]
     */
    public static function drivers(): array
    {
        return array_merge(['auto'], PlaceholderGenerator::availableDrivers());
    }
}
