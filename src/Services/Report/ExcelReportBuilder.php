<?php
declare(strict_types=1);

namespace App\Services\Report;

use App\Support\Lang;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Tabular data export of the project report (§5) — project header, interventions,
 * materials used. Unlike the PDF, this intentionally has no embedded photos: Excel
 * is the data-export format here, the PDF is the visual/printable one.
 */
final class ExcelReportBuilder
{
    public function build(array $data): string
    {
        $project = $data['project'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');

        $row = 1;
        $sheet->setCellValue("A{$row}", $project['name']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $row += 2;

        $headerFields = [
            'Cliente'              => $project['client_name'],
            'Località'             => $project['location'],
            'Data inizio'          => $project['start_date'],
            'Data fine'            => $project['end_date'],
            'Riferimento fattura'  => $project['invoice_reference'],
            'Stato'                => Lang::label('project_status', $project['status']),
        ];
        foreach ($headerFields as $label => $value) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->setCellValue("B{$row}", (string) $value);
            $row++;
        }
        $row += 1;

        $row = $this->writeInterventions($sheet, $row, $data['interventions']);
        $row += 1;
        $this->writeMaterials($sheet, $row, $data['materials']);

        foreach (['A', 'B', 'C', 'D'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        return (string) ob_get_clean();
    }

    private function writeInterventions(Worksheet $sheet, int $row, array $interventions): int
    {
        $sheet->setCellValue("A{$row}", 'Interventi');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $row++;

        $headers = ['Titolo', 'Data', 'Operaio', 'Stato'];
        foreach ($headers as $i => $label) {
            $col = chr(ord('A') + $i);
            $sheet->setCellValue("{$col}{$row}", $label);
            $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true);
        }
        $row++;

        foreach ($interventions as $iv) {
            $sheet->setCellValue("A{$row}", $iv['title']);
            $sheet->setCellValue("B{$row}", (string) $iv['scheduled_date']);
            $sheet->setCellValue("C{$row}", $iv['worker_name'] ?? '—');
            $sheet->setCellValue("D{$row}", Lang::label('intervention_status', $iv['status']));
            $row++;
        }

        return $row;
    }

    private function writeMaterials(Worksheet $sheet, int $row, array $materials): void
    {
        $sheet->setCellValue("A{$row}", 'Materiali utilizzati');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $row++;

        $headers = ['Articolo', 'Unità', 'Quantità totale'];
        foreach ($headers as $i => $label) {
            $col = chr(ord('A') + $i);
            $sheet->setCellValue("{$col}{$row}", $label);
            $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true);
        }
        $row++;

        if ($materials === []) {
            $sheet->setCellValue("A{$row}", 'Nessun materiale registrato come utilizzato.');
            return;
        }

        foreach ($materials as $m) {
            $sheet->setCellValue("A{$row}", $m['item_name']);
            $sheet->setCellValue("B{$row}", Lang::label('units', $m['unit']));
            $sheet->setCellValue("C{$row}", rtrim(rtrim((string) $m['total_qty'], '0'), '.'));
            $row++;
        }
    }
}
