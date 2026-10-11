<?php namespace Bonniss26\Imagery\Classes;

/**
 * Derives the key a placeholder is cached under.
 *
 * Built only from what changes the output: the source, the options that are
 * significant for the mode (see Defaults::SIGNIFICANT_KEYS), and - for a mode
 * that encodes an image - the format that will actually be produced. That last
 * one is not the `format` option: without WebP the library silently produces
 * JPEG at roughly nine times the size, so two application servers sharing one
 * database, one of them without WebP, would otherwise agree on a key while
 * disagreeing about what it holds.
 *
 * Not built from the source's identity (mtime, size). That is stored beside
 * the entry and compared on read, so an image overwritten in place updates its
 * row instead of leaving the old one behind as an orphan.
 */
final class CacheKey
{
    /**
     * Options added after 1.0, left out of the key while at these values.
     *
     * At its default an option changes nothing, and including it anyway would
     * change every key written by 1.0 - the whole cache would miss at once
     * after an upgrade, and a site without a queue or `lazy` would lose every
     * placeholder until someone ran imagery:warm.
     */
    const OMIT_AT_DEFAULT = [
        'smooth' => false,
        'background' => null,
    ];

    /**
     * @param array $options Resolved generation options.
     * @param string|null $effectiveFormat The format the driver will really emit,
     *                                     or null when the mode emits no image.
     */
    public static function make(string $sourceKey, array $options, ?string $effectiveFormat): string
    {
        $mode = (string) ($options['mode'] ?? '');

        $significant = [];
        foreach (Defaults::significantKeys($mode) as $key) {
            $value = $options[$key] ?? null;
            if (array_key_exists($key, self::OMIT_AT_DEFAULT) && $value === self::OMIT_AT_DEFAULT[$key]) {
                continue;
            }
            $significant[$key] = $value;
        }

        if (array_key_exists('format', $significant)) {
            $significant['format'] = $effectiveFormat;
        }

        ksort($significant);

        return sha1($sourceKey . "\n" . json_encode($significant));
    }
}
