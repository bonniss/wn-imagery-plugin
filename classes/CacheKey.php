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
     * @param array $options Resolved generation options.
     * @param string|null $effectiveFormat The format the driver will really emit,
     *                                     or null when the mode emits no image.
     */
    public static function make(string $sourceKey, array $options, ?string $effectiveFormat): string
    {
        $mode = (string) ($options['mode'] ?? '');

        $significant = [];
        foreach (Defaults::significantKeys($mode) as $key) {
            $significant[$key] = $options[$key] ?? null;
        }

        if (array_key_exists('format', $significant)) {
            $significant['format'] = $effectiveFormat;
        }

        ksort($significant);

        return sha1($sourceKey . "\n" . json_encode($significant));
    }
}
