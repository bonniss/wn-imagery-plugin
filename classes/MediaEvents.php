<?php namespace Bonniss26\Imagery\Classes;

use Throwable;

/**
 * Keeps the cache in step with what editors do to images in the backend.
 *
 * - An upload (Media Manager or a file attachment) is prepared at once, so a
 *   new image has its placeholder before any page shows it.
 * - A rename or move forgets the old path and prepares the new one; the
 *   cache key contains the path, so the old rows would only be orphans.
 * - A delete forgets the image.
 *
 * Plugin.php binds these to Winter's events; this class holds the decisions,
 * so they are testable without Winter. Every method swallows every failure:
 * it runs inside the editor's upload or rename request, which a placeholder
 * must never break.
 */
final class MediaEvents
{
    /** @var Imagery */
    private $imagery;

    /** @var string The Media Library's folder on its disk, e.g. "media/". */
    private $storageFolder;

    public function __construct(Imagery $imagery, string $storageFolder)
    {
        $this->imagery = $imagery;
        $this->storageFolder = $storageFolder;
    }

    /**
     * `media.file.upload` passes the path on the storage disk, with the
     * library's own folder in front ("media/photos/a.jpg").
     */
    public function uploaded(string $diskPath): void
    {
        $this->attempt(function () use ($diskPath) {
            $folder = trim(str_replace('\\', '/', $this->storageFolder), '/');
            $path = ltrim(str_replace('\\', '/', $diskPath), '/');
            if ($folder !== '' && strpos($path, $folder . '/') === 0) {
                $path = substr($path, strlen($folder) + 1);
            }

            $this->imagery->prepare(self::mediaKey($path), true);
        });
    }

    /** `media.file.rename`: both paths are library paths. */
    public function renamed(string $from, string $to): void
    {
        $this->attempt(function () use ($from, $to) {
            $this->imagery->forget(self::mediaKey($from));
            $this->imagery->prepare(self::mediaKey($to), true);
        });
    }

    /** `media.file.move`: the file keeps its name and lands in $folder. */
    public function moved(string $path, string $folder): void
    {
        $this->renamed($path, rtrim(str_replace('\\', '/', $folder), '/') . '/' . basename(str_replace('\\', '/', $path)));
    }

    /** `media.file.delete` */
    public function deleted(string $path): void
    {
        $this->attempt(function () use ($path) {
            $this->imagery->forget(self::mediaKey($path));
        });
    }

    /**
     * A System\Models\File was created - its data is on the disk by then, as
     * Winter writes it in beforeSave(). Only images are worth decoding.
     */
    public function fileCreated(object $file): void
    {
        $this->attempt(function () use ($file) {
            if (method_exists($file, 'isImage') && !$file->isImage()) {
                return;
            }

            $this->imagery->prepare($file);
        });
    }

    public function fileDeleted(object $file): void
    {
        $this->attempt(function () use ($file) {
            if (!empty($file->id)) {
                $this->imagery->forget('file:' . $file->id);
            }
        });
    }

    /** The Source key for a library path, normalised as SourceResolver does. */
    private static function mediaKey(string $path): string
    {
        return 'media:/' . ltrim(str_replace('\\', '/', $path), '/');
    }

    private function attempt(callable $action): void
    {
        try {
            $action();
        } catch (Throwable $e) {
            // See the class docblock.
        }
    }
}
