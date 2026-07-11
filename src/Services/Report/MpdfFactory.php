<?php
declare(strict_types=1);

namespace App\Services\Report;

use App\Support\Config;
use Mpdf\Mpdf;
use RuntimeException;

/**
 * Builds configured Mpdf instances for every PDF builder in one place.
 *
 * mPDF writes a font cache and image temp files to its `tempDir` on each run.
 * Its default (vendor/mpdf/mpdf/tmp) is root-owned and read-only in the
 * production container, where PHP-FPM runs as www-data — so an unconfigured
 * instance throws "Temporary files directory ... is not writable" and every
 * PDF endpoint 500s. Pointing tempDir at the writable storage/ tree fixes it.
 */
final class MpdfFactory
{
    public static function create(array $options = []): Mpdf
    {
        $options['tempDir'] = self::tempDir();

        return new Mpdf($options);
    }

    /** Resolve the configured scratch dir, creating it on first use. */
    private static function tempDir(): string
    {
        $dir = (string) Config::get('storage.pdf_temp_path');

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create mPDF temp directory: {$dir}");
        }

        return $dir;
    }
}
