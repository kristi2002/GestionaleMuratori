<?php
declare(strict_types=1);

namespace App\Services\Report;

use App\Support\View;
use Mpdf\Output\Destination;

/** Renders views/reports/purchase_order_pdf.php to a printable A4 order ("buono d'ordine"). */
final class PurchaseOrderPdfBuilder
{
    public function build(array $data): string
    {
        $html = View::render('reports/purchase_order_pdf', $data, null);

        $mpdf = MpdfFactory::create([
            'format'        => 'A4',
            'margin_top'    => 15,
            'margin_bottom' => 15,
            'margin_left'   => 15,
            'margin_right'  => 15,
        ]);
        $mpdf->SetTitle('Buono d\'Ordine ' . $data['order']['number']);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
