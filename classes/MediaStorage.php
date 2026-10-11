<?php namespace Bonniss26\Imagery\Classes;

/**
 * The few things SourceResolver needs from the Media Library.
 *
 * An interface so the resolution logic - which is where the security decisions
 * live - can be tested without a Winter application. WinterMediaStorage is the
 * real implementation.
 */
interface MediaStorage
{
    /** The public URL prefix media paths are served under, e.g. "/storage/app/media". */
    public function urlPrefix(): string;

    /** @param string $path Media-relative, with a leading slash. */
    public function exists(string $path): bool;

    public function get(string $path): ?string;

    /** Changes when the file is replaced; "" if unknown. */
    public function identity(string $path): string;

    /**
     * Every image in the library, media-relative.
     *
     * @return iterable<string>
     */
    public function images(): iterable;
}
