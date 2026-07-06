<?php
declare(strict_types=1);

namespace App\Services\Report;

use App\Support\Lang;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Builds the monthly accountant .xlsx (Esportazione per il Commercialista): a
 * summary sheet plus material costs, worker hours and per-cantiere costs — raw
 * tabular data an accounting office can import (e.g. TeamSystem).
 */
final class AccountantExportBuilder
{
    public function build(array $data): string
    {
        $spreadsheet = new Spreadsheet();

        $this->summarySheet($spreadsheet->getActiveSheet(), $data);
        $this->materialsSheet($spreadsheet->createSheet(), $data['materials']);
        $this->laborSheet($spreadsheet->createSheet(), $data['labor']);
        $this->projectsSheet($spreadsheet->createSheet(), $data['projects']);

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        return (string) ob_get_clean();
    }

    private function summarySheet(Worksheet $sheet, array $data): void
    {
        $sheet->setTitle('Riepilogo');
        $sheet->setCellValue('A1', 'Riepilogo costi');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $rows = [
            ['Periodo', $data['from'] . ' — ' . $data['to']],
            ['Costo materiali (€)', number_format($data['totals']['material_cost'], 2, ',', '.')],
            ['Ore lavoro totali', number_format($data['totals']['hours'], 2, ',', '.')],
        ];
        $r = 3;
        foreach ($rows as [$label, $value]) {
            $sheet->setCellValue("A{$r}", $label);
            $sheet->getStyle("A{$r}")->getFont()->setBold(true);
            $sheet->setCellValue("B{$r}", (string) $value);
            $r++;
        }
        $this->autosize($sheet, ['A', 'B']);
    }

    private function materialsSheet(Worksheet $sheet, array $materials): void
    {
        $sheet->setTitle('Materiali');
        $this->headerRow($sheet, ['Articolo', 'Unità', 'Quantità', 'Costo unitario (€)', 'Costo totale (€)']);

        $r = 2;
        foreach ($materials as $m) {
            $sheet->setCellValue("A{$r}", $m['item_name']);
            $sheet->setCellValue("B{$r}", Lang::label('units', $m['unit']));
            $sheet->setCellValue("C{$r}", (float) $m['total_qty']);
            $sheet->setCellValue("D{$r}", $m['unit_cost'] !== null ? (float) $m['unit_cost'] : 0);
            $sheet->setCellValue("E{$r}", round((float) $m['total_cost'], 2));
            $r++;
        }
        $this->autosize($sheet, ['A', 'B', 'C', 'D', 'E']);
    }

    private function laborSheet(Worksheet $sheet, array $labor): void
    {
        $sheet->setTitle('Ore Lavoro');
        $this->headerRow($sheet, ['Operaio', 'Ore', 'Turni']);

        $r = 2;
        foreach ($labor as $l) {
            $sheet->setCellValue("A{$r}", $l['worker_name']);
            $sheet->setCellValue("B{$r}", round((float) $l['hours'], 2));
            $sheet->setCellValue("C{$r}", (int) $l['shifts']);
            $r++;
        }
        $this->autosize($sheet, ['A', 'B', 'C']);
    }

    private function projectsSheet(Worksheet $sheet, array $projects): void
    {
        $sheet->setTitle('Costi per Cantiere');
        $this->headerRow($sheet, ['Cantiere', 'Cliente', 'Costo materiali (€)']);

        $r = 2;
        foreach ($projects as $p) {
            $sheet->setCellValue("A{$r}", $p['project_name']);
            $sheet->setCellValue("B{$r}", $p['client_name']);
            $sheet->setCellValue("C{$r}", round((float) $p['material_cost'], 2));
            $r++;
        }
        $this->autosize($sheet, ['A', 'B', 'C']);
    }

    private function headerRow(Worksheet $sheet, array $labels): void
    {
        foreach ($labels as $i => $label) {
            $col = chr(ord('A') + $i);
            $sheet->setCellValue("{$col}1", $label);
            $sheet->getStyle("{$col}1")->getFont()->setBold(true);
        }
    }

    private function autosize(Worksheet $sheet, array $cols): void
    {
        foreach ($cols as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
