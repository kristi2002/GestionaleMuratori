<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Config;
use App\Support\Storage\Storage;
use App\Support\Storage\StorageInterface;

/**
 * Streams stored images (photos, thumbnails, signatures) to the client.
 * Authorization is the caller's job — controllers run their role/ownership
 * guards first, then delegate the actual file I/O here.
 */
final class PhotoStreamService
{
    private StorageInterface $storage;

    public function __construct(?StorageInterface $storage = null)
    {
        $this->storage = $storage ?? Storage::disk();
    }

    /** @param array<string,mixed> $photo photos table row */
    public function streamPhoto(array $photo, bool $original): bool
    {
        $relPath = $original
            ? (string) $photo['file_path']
            : (string) ($photo['thumb_path'] ?? $photo['file_path']);
        return $this->streamFile($relPath);
    }

    /** Streams a stored image by relative path; returns false when it does not exist. */
    public function streamFile(?string $relPath): bool
    {
        if ($relPath === null || $relPath === '' || !$this->storage->exists($relPath)) {
            return false;
        }

        header('Content-Type: ' . (str_ends_with($relPath, '.png') ? 'image/png' : 'image/jpeg'));
        header('Cache-Control: private, max-age=86400');
        echo $this->storage->get($relPath);
        return true;
    }
}
