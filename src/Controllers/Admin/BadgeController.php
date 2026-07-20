<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\CompanySettingsModel;
use App\Models\UserModel;
use App\Services\Report\BadgePdfBuilder;
use App\Services\Report\ReportFilename;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\Storage\Storage;
use App\Support\View;

/**
 * Tessera di riconoscimento (Art. 18 c.1 lett. u D.Lgs 81/2008, L. 136/2010 art. 5):
 * a printable site badge for staff who work on cantieri — photo, worker, employer,
 * hire date. Built from the existing user record; the employer is the company profile.
 */
final class BadgeController
{
    /** Roles that operate on site and therefore need a badge. */
    private const BADGEABLE = ['worker', 'admin'];

    /** GET /admin/users/{id}/badge — the worker's tessera as a PDF. */
    public function pdf(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $worker = (new UserModel())->findById((int) $id);
        if ($worker === null || !in_array((string) $worker['role'], self::BADGEABLE, true)) {
            Response::html(View::render('errors/404', ['title' => Lang::get('admin.users.not_found')], 'layout'), 404);
            return;
        }

        $pdf = (new BadgePdfBuilder())->build([
            'worker'  => $worker,
            'company' => (new CompanySettingsModel())->get(),
            'photo'   => $this->avatarDataUri($worker['avatar_path'] ?? null),
        ]);
        $filename = ReportFilename::make((string) $worker['name'], 'pdf', 'tessera');

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
    }

    /** Read the stored avatar and inline it as a data: URI for mPDF (or null). */
    private function avatarDataUri(?string $relPath): ?string
    {
        if ($relPath === null || $relPath === '') {
            return null;
        }
        $storage = Storage::disk();
        if (!$storage->exists($relPath)) {
            return null;
        }
        $mime = str_ends_with($relPath, '.png') ? 'image/png' : 'image/jpeg';
        return 'data:' . $mime . ';base64,' . base64_encode($storage->get($relPath));
    }
}
