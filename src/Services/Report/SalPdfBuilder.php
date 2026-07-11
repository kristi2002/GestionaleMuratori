<?php
declare(strict_types=1);

namespace App\Services\Report;

use App\Support\View;
use Mpdf\Output\Destination;

/**
 * Renders an S.A.L. (Stato Avanzamento Lavori) document to a printable A4 PDF.
 * Used when a draft is issued: the resulting PDF is the locked artifact the
 * Direttore dei Lavori signs.
 *
 * @param array{document:array<string,mixed>,lines:array<int,array<string,mixed>>} $data
 */
final class SalPdfBuilder
{
    public function build(array $data): string
    {
        $html = View::render('reports/sal', $data, null);

        $mpdf = MpdfFactory::create([
            'format'        => 'A4',
            'margin_top'    => 18,
            'margin_bottom' => 18,
            'margin_left'   => 15,
            'margin_right'  => 15,
        ]);
        $mpdf->SetTitle('S.A.L. n. ' . $data['document']['number'] . ' — ' . $data['document']['project_name']);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
