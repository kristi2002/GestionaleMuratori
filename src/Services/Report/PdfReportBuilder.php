<?php
declare(strict_types=1);

namespace App\Services\Report;

use App\Support\View;
use Mpdf\Output\Destination;

/** Renders the report HTML template (views/reports/pdf.php) to a printable A4 PDF (§5). */
final class PdfReportBuilder
{
    public function build(array $data): string
    {
        $html = View::render('reports/pdf', $data, null);

        $mpdf = MpdfFactory::create([
            'format'        => 'A4',
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'margin_left'   => 15,
            'margin_right'  => 15,
        ]);
        $mpdf->SetTitle('Report — ' . $data['project']['name']);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
