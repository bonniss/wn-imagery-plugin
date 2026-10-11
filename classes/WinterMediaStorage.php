<?php namespace Bonniss26\Imagery\Classes;

use Config;
use System\Classes\MediaLibrary;
use System\Classes\MediaLibraryItem;

/**
 * MediaStorage on top of Winter's Media Library, whatever disk it lives on.
 */
class WinterMediaStorage implements MediaStorage
{
    public function urlPrefix(): string
    {
        // May be a full CDN URL; only its path can appear in what `|media` emits
        // for a relative link, and SourceResolver compares paths.
        $prefix = (string) Config::get('cms.storage.media.path', '/storage/app/media');

        return (string) (parse_url($prefix, PHP_URL_PATH) ?: $prefix);
    }

    public function exists(string $path): bool
    {
        return MediaLibrary::instance()->exists($path);
    }

    public function get(string $path): ?string
    {
        $contents = MediaLibrary::instance()->get($path);

        return is_string($contents) ? $contents : null;
    }

    public function identity(string $path): string
    {
        $library = MediaLibrary::instance();
        $disk = $library->getStorageDisk();
        $full = $library->getMediaPath(MediaLibrary::validatePath($path));

        return $disk->lastModified($full) . ':' . $disk->size($full);
    }

    public function images(): iterable
    {
        $library = MediaLibrary::instance();

        foreach ($library->listAllDirectories() as $folder) {
            $items = $library->listFolderContents($folder, false, MediaLibraryItem::FILE_TYPE_IMAGE, true);
            foreach ($items as $item) {
                if ($item->type === MediaLibraryItem::TYPE_FILE) {
                    yield $item->path;
                }
            }
        }
    }
}
