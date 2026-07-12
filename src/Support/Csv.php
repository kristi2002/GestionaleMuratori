<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Streams a CSV download. Uses ';' (Italian Excel default) and a UTF-8 BOM so
 * accented text opens correctly in Excel.
 */
final class Csv
{
    /**
     * @param array<int,string>        $header
     * @param array<int,array<int,mixed>> $rows
     */
    public static function send(string $filename, array $header, array $rows): void
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM
        fputcsv($out, $header, ';');
        foreach ($rows as $row) {
            fputcsv($out, array_map(static fn ($v): string => (string) ($v ?? ''), $row), ';');
        }
        fclose($out);
    }
}
