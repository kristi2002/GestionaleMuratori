<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\ProjectModel;
use App\Services\Report\AccountantExportBuilder;
use App\Services\Report\AccountantExportDataService;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/**
 * Esportazione per il Commercialista: a monthly .xlsx of material costs and worker
 * hours the accounting office can import. Admin-only.
 */
final class ExportController
{
    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        Response::html(View::render('admin/exports/index', [
            'title'        => Lang::get('admin.exports.title'),
            'currentMonth' => (new \DateTimeImmutable('first day of this month'))->format('Y-m'),
            'projects'     => (new ProjectModel())->all(),
        ], 'layout'));
    }

    /** GET /admin/exports/accountant?month=YYYY-MM — download the monthly workbook. */
    public function accountant(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $month = (string) $request->input('month', '');
        $start = \DateTimeImmutable::createFromFormat('Y-m-d', $month . '-01');
        if ($start === false || $start->format('Y-m') !== $month) {
            $start = new \DateTimeImmutable('first day of this month');
        }
        $start = $start->setTime(0, 0);
        $end   = $start->modify('+1 month');

        $data = (new AccountantExportDataService())->build(
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        );
        $xlsx = (new AccountantExportBuilder())->build($data);

        $filename = 'commercialista-' . $start->format('Y-m') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($xlsx));
        echo $xlsx;
    }
}
