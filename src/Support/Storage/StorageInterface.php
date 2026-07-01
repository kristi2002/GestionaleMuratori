<?php
declare(strict_types=1);

namespace App\Support\Storage;

/**
 * Storage abstraction for uploaded files (§2 — keeps controllers swappable to S3
 * later without changes). All paths are relative to the storage root.
 */
interface StorageInterface
{
    public function put(string $relativePath, string $contents): void;

    /** Move a just-uploaded ($_FILES tmp_name) file into storage. */
    public function putUploadedFile(string $relativePath, string $tmpPath): void;

    public function get(string $relativePath): string;

    public function exists(string $relativePath): bool;

    /** Absolute filesystem path — used by GD and other local-only operations. */
    public function absolutePath(string $relativePath): string;

    public function delete(string $relativePath): void;
}
