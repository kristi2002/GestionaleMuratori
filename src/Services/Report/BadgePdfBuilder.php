<?php
declare(strict_types=1);

namespace App\Services\Report;

use App\Support\View;
use Mpdf\Output\Destination;

/**
 * Renders views/reports/badge_pdf.php to a lanyard-sized (90×120mm) worker badge
 * — the tessera di riconoscimento required on construction sites (Art. 18 c.1
 * lett. u D.Lgs 81/2008, L. 136/2010 art. 5): photo, worker, employer, hire date.
 */
final class BadgePdfBuilder
{
    public function build(array $data): string
    {
        $html = View::render('reports/badge_pdf', $data, null);

        $mpdf = MpdfFactory::create([
            'format'        => [90, 120],
            'margin_top'    => 6,
            'margin_bottom' => 6,
            'margin_left'   => 6,
            'margin_right'  => 6,
        ]);
        $mpdf->SetTitle('Tessera ' . ($data['worker']['name'] ?? ''));
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', Destination::STRING_RETURN);
    }
}
