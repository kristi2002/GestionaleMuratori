<?php
declare(strict_types=1);

namespace App\Services\Report;

/** Shared by Admin\ReportController and Client\ReportController. */
final class ReportFilename
{
    public static function make(string $projectName, string $extension): string
    {
        $slug = trim((string) preg_replace('/[^a-z0-9]+/i', '-', $projectName), '-');
        return 'report-' . strtolower($slug) . '.' . $extension;
    }
}
