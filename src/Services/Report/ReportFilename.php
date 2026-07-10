<?php
declare(strict_types=1);

namespace App\Services\Report;

/** Shared by Admin\ReportController and Client\ReportController. */
final class ReportFilename
{
    /**
     * Build a safe download filename: "{prefix}-{slug}.{extension}".
     * The $prefix lets callers produce "fattura-…"/"preventivo-…" instead of the
     * default "report-…" (invoices and quotes rely on it).
     */
    public static function make(string $name, string $extension, string $prefix = 'report'): string
    {
        $slug       = trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-');
        $prefixSlug = trim((string) preg_replace('/[^a-z0-9]+/i', '-', $prefix), '-');
        $prefixSlug = $prefixSlug !== '' ? strtolower($prefixSlug) : 'report';
        return $prefixSlug . '-' . strtolower($slug) . '.' . $extension;
    }
}
