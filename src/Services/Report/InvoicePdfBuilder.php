<?php
declare(strict_types=1);

namespace App\Services\Report;

use App\Support\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/** Renders views/reports/invoice_pdf.php to a printable A4 receipt. */
final class InvoicePdfBuilder
{
    public function build(array $data): string
    {
        $html = View::render('reports/invoice_pdf', $data, null);

        $mpdf = new Mpdf([
            'format'        => 'A4',
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'margin_left'   => 15,
            'margin_right'  => 15,
        ]);
        $mpdf->SetTitle('Fattura ' . $data['invoice']['number']);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
