<?php namespace Bonniss26\Imagery\Tests\Fakes;

use Bonniss26\Imagery\Classes\MediaStorage;

/**
 * A Media Library held in an array: path => [bytes, identity]. Counts reads so
 * tests can prove a cache hit never touched the disk.
 */
final class FakeMediaStorage implements MediaStorage
{
    /** @var array<string,array{0:string,1:string}> */
    public $files = [];

    /** @var int */
    public $reads = 0;

    public function put(string $path, string $bytes, string $identity = 'v1'): void
    {
        $this->files[$path] = [$bytes, $identity];
    }

    public function urlPrefix(): string
    {
        return '/storage/app/media';
    }

    public function exists(string $path): bool
    {
        return isset($this->files[$path]);
    }

    public function get(string $path): ?string
    {
        $this->reads++;

        return $this->files[$path][0] ?? null;
    }

    public function identity(string $path): string
    {
        return $this->files[$path][1] ?? '';
    }

    public function images(): iterable
    {
        return array_keys($this->files);
    }
}
