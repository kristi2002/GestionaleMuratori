<?php
declare(strict_types=1);

namespace App\Support\Storage;

use RuntimeException;

final class LocalStorage implements StorageInterface
{
    public function __construct(private string $root)
    {
    }

    public function put(string $relativePath, string $contents): void
    {
        $path = $this->absolutePath($relativePath);
        $this->ensureDirFor($path);
        if (file_put_contents($path, $contents) === false) {
            throw new RuntimeException("Impossibile scrivere il file: {$relativePath}");
        }
    }

    public function putUploadedFile(string $relativePath, string $tmpPath): void
    {
        $path = $this->absolutePath($relativePath);
        $this->ensureDirFor($path);
        if (!move_uploaded_file($tmpPath, $path)) {
            throw new RuntimeException("Impossibile salvare il file caricato: {$relativePath}");
        }
    }

    public function get(string $relativePath): string
    {
        $contents = file_get_contents($this->absolutePath($relativePath));
        if ($contents === false) {
            throw new RuntimeException("File non trovato: {$relativePath}");
        }
        return $contents;
    }

    public function exists(string $relativePath): bool
    {
        return is_file($this->absolutePath($relativePath));
    }

    public function absolutePath(string $relativePath): string
    {
        return rtrim($this->root, '/\\') . '/' . ltrim($relativePath, '/\\');
    }

    public function delete(string $relativePath): void
    {
        $path = $this->absolutePath($relativePath);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function ensureDirFor(string $absoluteFilePath): void
    {
        $dir = dirname($absoluteFilePath);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Impossibile creare la cartella: {$dir}");
        }
    }
}
