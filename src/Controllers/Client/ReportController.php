<?php
declare(strict_types=1);

namespace App\Controllers\Client;

use App\Http\Middleware\AuthGuard;
use App\Http\Middleware\ClientProjectGuard;
use App\Services\Report\ExcelReportBuilder;
use App\Services\Report\PdfReportBuilder;
use App\Services\Report\ReportDataService;
use App\Services\Report\ReportFilename;
use App\Support\Auth;
use App\Support\Request;

final class ReportController
{
    public function pdf(Request $request, string $id): void
    {
        AuthGuard::require($request, ['client']);
        if (ClientProjectGuard::require($request, $id, (int) Auth::clientId()) === null) {
            return;
        }

        $data     = (new ReportDataService())->build((int) $id);
        $pdf      = (new PdfReportBuilder())->build($data);
        $filename = ReportFilename::make($data['project']['name'], 'pdf');

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
    }

    public function excel(Request $request, string $id): void
    {
        AuthGuard::require($request, ['client']);
        if (ClientProjectGuard::require($request, $id, (int) Auth::clientId()) === null) {
            return;
        }

        $data     = (new ReportDataService())->build((int) $id);
        $xlsx     = (new ExcelReportBuilder())->build($data);
        $filename = ReportFilename::make($data['project']['name'], 'xlsx');

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($xlsx));
        echo $xlsx;
    }
}
