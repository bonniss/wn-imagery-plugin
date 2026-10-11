<?php namespace Bonniss26\Imagery\Classes;

use Closure;
use Throwable;

/**
 * Turns whatever a template hands a filter into a Source, or null.
 *
 * Accepted: a Media Library path (with or without the media URL prefix that
 * `|media` adds), a `System\Models\File`, an attachOne/attachMany value (the
 * first file), and null. Raw filesystem paths only when explicitly enabled,
 * and only inside `allowed_roots`: a filter reachable from a template would
 * otherwise turn any user-controlled string into a way to probe the
 * filesystem.
 *
 * Never throws - a template must not break because an image is missing.
 */
final class SourceResolver
{
    /** @var MediaStorage */
    private $media;

    /** @var Closure(int): ?object Finds a System\Models\File by id. */
    private $findFile;

    /** @var bool */
    private $allowAbsolutePaths;

    /** @var string[] Real paths, each ending in a directory separator. */
    private $allowedRoots;

    /** @var bool */
    private $trackRemoteIdentity;

    /**
     * @param array $sources The `sources` config block.
     * @param bool $trackRemoteIdentity False for `remote_identity = path_only`.
     */
    public function __construct(MediaStorage $media, Closure $findFile, array $sources = [], bool $trackRemoteIdentity = true)
    {
        $this->media = $media;
        $this->findFile = $findFile;
        $this->allowAbsolutePaths = (bool) ($sources['allow_absolute_paths'] ?? false);
        $this->allowedRoots = self::normalizeRoots((array) ($sources['allowed_roots'] ?? []));
        $this->trackRemoteIdentity = $trackRemoteIdentity;
    }

    /**
     * @param mixed $input
     */
    public function resolve($input): ?Source
    {
        try {
            if ($input === null || $input === '' || $input === false) {
                return null;
            }

            if (is_string($input)) {
                return $this->fromString($input);
            }

            if (is_object($input)) {
                return $this->fromObject($input);
            }
        } catch (Throwable $e) {
            // Fall through: an input we cannot make sense of is simply "no image".
        }

        return null;
    }

    /**
     * Re-resolves a key produced by Source::key(), as a queued job must.
     */
    public function fromKey(string $key): ?Source
    {
        [$type, $value] = array_pad(explode(':', $key, 2), 2, '');

        try {
            switch ($type) {
                case 'media':
                    return $this->media($value);
                case 'file':
                    $file = ctype_digit($value) ? ($this->findFile)((int) $value) : null;

                    return $file ? $this->file($file) : null;
                case 'path':
                    return $this->absolute($value);
            }
        } catch (Throwable $e) {
        }

        return null;
    }

    private function fromString(string $input): ?Source
    {
        $input = trim($input);

        // Accept the output of `|media` too, so `{{ photo|media|lqip }}` and
        // `{{ photo|lqip }}` mean the same thing.
        $path = parse_url($input, PHP_URL_PATH);
        if (is_string($path)) {
            $prefix = rtrim($this->media->urlPrefix(), '/');
            $decoded = rawurldecode($path);
            if ($prefix !== '' && strpos($decoded, $prefix . '/') === 0) {
                return $this->media(substr($decoded, strlen($prefix)));
            }
        }

        // Anything with a scheme or host that is not our media URL is someone
        // else's image. Fetching it would make this filter an SSRF primitive.
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $input) || strpos($input, '//') === 0) {
            return null;
        }

        if ($this->allowAbsolutePaths && self::looksAbsolute($input) && $this->isInsideAllowedRoot($input)) {
            return $this->absolute($input);
        }

        return $this->media($input);
    }

    private function fromObject(object $input): ?Source
    {
        // attachMany relations and collections: the first file.
        if ($input instanceof \Traversable || method_exists($input, 'first')) {
            $first = method_exists($input, 'first') ? $input->first() : null;
            if ($first === null && $input instanceof \Traversable) {
                foreach ($input as $item) {
                    $first = $item;
                    break;
                }
            }

            return is_object($first) && !($first instanceof \Traversable) ? $this->fromObject($first) : null;
        }

        // System\Models\File, duck-typed so this class loads without Winter.
        if (isset($input->disk_name) && method_exists($input, 'getContents')) {
            return $this->file($input);
        }

        return null;
    }

    private function media(string $path): ?Source
    {
        $path = '/' . ltrim(str_replace('\\', '/', $path), '/');

        // Winter's own MediaLibrary::validatePath() rejects these too, by throwing;
        // refusing here keeps a traversal attempt from ever reaching the disk.
        if ($path === '/' || preg_match('#(^|/)\.\.(/|$)#', $path) || strpos($path, "\0") !== false) {
            return null;
        }

        if (!$this->media->exists($path)) {
            return null;
        }

        $media = $this->media;

        return new Source(
            'media:' . $path,
            function () use ($media, $path) {
                return $this->trackRemoteIdentity ? $media->identity($path) : '';
            },
            function () use ($media, $path) {
                return $media->get($path);
            }
        );
    }

    private function file(object $file): ?Source
    {
        if (empty($file->id)) {
            return null;
        }

        // Straight from the database row: no round trip to the disk, whatever
        // `remote_identity` says. A replaced attachment is a new row anyway.
        $identity = ($file->file_size ?? '') . ':' . (string) ($file->updated_at ?? '');

        return new Source(
            'file:' . $file->id,
            function () use ($identity) {
                return $identity;
            },
            function () use ($file) {
                return $file->getContents();
            }
        );
    }

    private function absolute(string $path): ?Source
    {
        if (!$this->allowAbsolutePaths || !$this->isInsideAllowedRoot($path)) {
            return null;
        }

        $real = realpath($path);
        if ($real === false || !is_file($real) || !is_readable($real)) {
            return null;
        }

        return new Source(
            'path:' . $real,
            function () use ($real) {
                clearstatcache(true, $real);

                return @filemtime($real) . ':' . @filesize($real);
            },
            function () use ($real) {
                return @file_get_contents($real);
            }
        );
    }

    /**
     * Resolved with realpath() before comparing, so neither `..` segments nor a
     * symlink inside an allowed root can lead outside it.
     */
    private function isInsideAllowedRoot(string $path): bool
    {
        $real = realpath($path);
        if ($real === false) {
            return false;
        }

        foreach ($this->allowedRoots as $root) {
            if (strpos($real . DIRECTORY_SEPARATOR, $root) === 0) {
                return true;
            }
        }

        return false;
    }

    private static function looksAbsolute(string $path): bool
    {
        return $path[0] === '/' && is_file($path)
            || (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);
    }

    /**
     * @return string[]
     */
    private static function normalizeRoots(array $roots): array
    {
        $normalized = [];
        foreach ($roots as $root) {
            $real = is_string($root) && $root !== '' ? realpath($root) : false;
            if ($real !== false && is_dir($real)) {
                $normalized[] = rtrim($real, '/\\') . DIRECTORY_SEPARATOR;
            }
        }

        return $normalized;
    }
}
