<?php namespace Bonniss26\Imagery\Classes;

use Closure;
use Throwable;

/**
 * An image a template asked for, resolved to something we can read.
 *
 * The key ("media:/photos/a.jpg", "file:42", "path:/srv/img/a.jpg") is stable
 * and re-resolvable, which is what lets a queued job find the same image again
 * from nothing but a string. Identity and bytes are lazy: on a remote disk each
 * costs a round trip, and a cache hit needs neither the bytes nor - with
 * `remote_identity` set to `path_only` - the identity.
 */
final class Source
{
    /** @var string */
    private $key;

    /** @var Closure(): string */
    private $identity;

    /** @var Closure(): ?string */
    private $bytes;

    /** @var string|null */
    private $resolvedIdentity;

    public function __construct(string $key, Closure $identity, Closure $bytes)
    {
        $this->key = $key;
        $this->identity = $identity;
        $this->bytes = $bytes;
    }

    public function key(): string
    {
        return $this->key;
    }

    /**
     * Something that changes when the file is replaced in place - typically
     * "mtime:size". Empty when identity is not tracked; never throws.
     */
    public function identity(): string
    {
        if ($this->resolvedIdentity === null) {
            try {
                $this->resolvedIdentity = (string) ($this->identity)();
            } catch (Throwable $e) {
                $this->resolvedIdentity = '';
            }
        }

        return $this->resolvedIdentity;
    }

    /** The raw file, or null if it cannot be read. Never throws. */
    public function bytes(): ?string
    {
        try {
            $bytes = ($this->bytes)();
        } catch (Throwable $e) {
            return null;
        }

        return is_string($bytes) && $bytes !== '' ? $bytes : null;
    }
}
