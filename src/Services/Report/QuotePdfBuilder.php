<?php
declare(strict_types=1);

namespace App\Services\Report;

use App\Support\View;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/** Renders views/reports/quote_pdf.php to a printable A4 estimate ("preventivo"). */
final class QuotePdfBuilder
{
    public function build(array $data): string
    {
        $html = View::render('reports/quote_pdf', $data, null);

        $mpdf = new Mpdf([
            'format'        => 'A4',
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'margin_left'   => 15,
            'margin_right'  => 15,
        ]);
        $mpdf->SetTitle('Preventivo ' . $data['quote']['number']);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
