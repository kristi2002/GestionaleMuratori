<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Services\Report\ExcelReportBuilder;
use App\Services\Report\PdfReportBuilder;
use App\Services\Report\ReportDataService;
use App\Services\Report\ReportFilename;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

final class ReportController
{
    public function pdf(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $data = (new ReportDataService())->build((int) $id);
        if ($data === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        $pdf      = (new PdfReportBuilder())->build($data);
        $filename = ReportFilename::make($data['project']['name'], 'pdf');

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
    }

    public function excel(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $data = (new ReportDataService())->build((int) $id);
        if ($data === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        $xlsx     = (new ExcelReportBuilder())->build($data);
        $filename = ReportFilename::make($data['project']['name'], 'xlsx');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($xlsx));
        echo $xlsx;
    }
}
