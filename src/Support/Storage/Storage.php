<?php
declare(strict_types=1);

namespace App\Support\Storage;

use App\Support\Config;
use RuntimeException;

/**
 * Selects the configured storage driver (config `storage.driver`) and returns it
 * behind StorageInterface, so controllers/services never construct a concrete
 * driver themselves. This is what makes the interface's promise real — uploads
 * can move to S3 (ADR-0001 Phase 1, a prerequisite for horizontal scale) by
 * adding one case here, with no call-site changes.
 */
final class Storage
{
    public static function disk(): StorageInterface
    {
        $driver = (string) Config::get('storage.driver', 'local');

        return match ($driver) {
            'local' => new LocalStorage((string) Config::get('storage.uploads_path')),
            // 's3' => new S3Storage(...),  // slots in here (Phase 1)
            default => throw new RuntimeException("Unknown storage driver: {$driver}"),
        };
    }
}
