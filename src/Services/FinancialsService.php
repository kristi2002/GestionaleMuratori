<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Database;
use PDO;

/**
 * Per-cantiere economic snapshot: cash in (invoiced / collected) vs cash out
 * (materials valued at unit_cost + logged expenses), and the resulting margin.
 *
 * Read-only. Figures are computed with separate grouped queries merged in PHP
 * (a single join across invoices + expenses + material movements would multiply
 * rows and double-count). "Invoiced" recognises issued+paid invoices as revenue;
 * "collected" is paid only.
 */
final class FinancialsService
{
    /** Margin below this fraction of revenue is flagged "thin". */
    private const THIN_MARGIN = 0.15;

    /** @return array{rows:array<int,array<string,mixed>>,totals:array<string,float>} */
    public function all(): array
    {
        $pdo = Database::pdo();

        $invoiced  = $this->keyedSum($pdo, "SELECT project_id AS k, SUM(COALESCE(amount,0)) AS v FROM project_invoices WHERE status IN ('issued','paid') GROUP BY project_id");
        $collected = $this->keyedSum($pdo, "SELECT project_id AS k, SUM(COALESCE(amount,0)) AS v FROM project_invoices WHERE status = 'paid' GROUP BY project_id");
        $expenses  = $this->keyedSum($pdo, "SELECT project_id AS k, SUM(amount) AS v FROM expenses WHERE project_id IS NOT NULL GROUP BY project_id");
        $materials = $this->keyedSum(
            $pdo,
            "SELECT i.project_id AS k, SUM(m.qty * COALESCE(w.unit_cost, 0)) AS v
             FROM stock_movements m
             JOIN warehouse_items w ON w.id = m.item_id
             JOIN interventions i ON i.id = m.intervention_id
             WHERE m.type = 'out'
             GROUP BY i.project_id"
        );

        $projects = $pdo->query(
            "SELECT p.id, p.name, p.status, c.name AS client_name
             FROM projects p JOIN clients c ON c.id = p.client_id"
        )->fetchAll();

        $rows   = [];
        $totals = ['invoiced' => 0.0, 'collected' => 0.0, 'cost' => 0.0, 'margin' => 0.0];

        foreach ($projects as $p) {
            $id       = (int) $p['id'];
            $inv      = (float) ($invoiced[$id] ?? 0);
            $col      = (float) ($collected[$id] ?? 0);
            $matCost  = (float) ($materials[$id] ?? 0);
            $expCost  = (float) ($expenses[$id] ?? 0);
            $cost     = $matCost + $expCost;
            $margin   = $inv - $cost;

            $rows[] = [
                'id'           => $id,
                'name'         => (string) $p['name'],
                'client_name'  => (string) $p['client_name'],
                'status'       => (string) $p['status'],
                'invoiced'     => $inv,
                'collected'    => $col,
                'outstanding'  => $inv - $col,
                'material_cost'=> $matCost,
                'expenses'     => $expCost,
                'cost'         => $cost,
                'margin'       => $margin,
                'margin_pct'   => $inv > 0 ? $margin / $inv * 100 : null,
                'health'       => $this->health($inv, $margin),
            ];

            $totals['invoiced']  += $inv;
            $totals['collected'] += $col;
            $totals['cost']      += $cost;
            $totals['margin']    += $margin;
        }

        // Most economically significant cantieri first.
        usort($rows, static fn (array $a, array $b): int => $b['invoiced'] <=> $a['invoiced']);

        $totals['margin_pct']  = $totals['invoiced'] > 0 ? $totals['margin'] / $totals['invoiced'] * 100 : null;
        $totals['outstanding'] = $totals['invoiced'] - $totals['collected'];

        // Monthly invoiced revenue (issued+paid) for the trailing 12 months,
        // gaps filled with 0 so the chart always shows a full year.
        $byMonth = [];
        foreach ($pdo->query(
            "SELECT DATE_FORMAT(issue_date,'%Y-%m') AS ym, SUM(COALESCE(amount,0)) AS v
             FROM project_invoices
             WHERE status IN ('issued','paid')
             GROUP BY ym"
        )->fetchAll() as $row) {
            $byMonth[(string) $row['ym']] = (float) $row['v'];
        }
        $shortMonths = ['', 'Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'];
        $months      = [];
        $first       = new \DateTimeImmutable('first day of this month');
        for ($i = 11; $i >= 0; $i--) {
            $m        = $first->modify("-{$i} months");
            $months[] = [
                'label' => $shortMonths[(int) $m->format('n')],
                'value' => (int) round($byMonth[$m->format('Y-m')] ?? 0),
            ];
        }
        $totals['current_month'] = (float) ($byMonth[$first->format('Y-m')] ?? 0);

        return ['rows' => $rows, 'totals' => $totals, 'months' => $months];
    }

    /** Same figures for a single cantiere (project detail page). @return array<string,mixed> */
    public function forProject(int $projectId): array
    {
        $pdo = Database::pdo();
        $inv = (float) $this->scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM project_invoices WHERE status IN ('issued','paid') AND project_id = ?", $projectId);
        $col = (float) $this->scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM project_invoices WHERE status = 'paid' AND project_id = ?", $projectId);
        $exp = (float) $this->scalar($pdo, "SELECT COALESCE(SUM(amount),0) FROM expenses WHERE project_id = ?", $projectId);
        $mat = (float) $this->scalar(
            $pdo,
            "SELECT COALESCE(SUM(m.qty * COALESCE(w.unit_cost,0)),0)
             FROM stock_movements m
             JOIN warehouse_items w ON w.id = m.item_id
             JOIN interventions i ON i.id = m.intervention_id
             WHERE m.type = 'out' AND i.project_id = ?",
            $projectId
        );
        $cost   = $mat + $exp;
        $margin = $inv - $cost;

        return [
            'invoiced'      => $inv,
            'collected'     => $col,
            'outstanding'   => $inv - $col,
            'material_cost' => $mat,
            'expenses'      => $exp,
            'cost'          => $cost,
            'margin'        => $margin,
            'margin_pct'    => $inv > 0 ? $margin / $inv * 100 : null,
            'health'        => $this->health($inv, $margin),
        ];
    }

    private function scalar(PDO $pdo, string $sql, int $projectId): mixed
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$projectId]);
        return $stmt->fetchColumn();
    }

    /** 'danger' (loss), 'warning' (thin), 'ok', or 'none' (no revenue yet). */
    private function health(float $invoiced, float $margin): string
    {
        if ($invoiced <= 0) {
            return 'none';
        }
        if ($margin < 0) {
            return 'danger';
        }
        return $margin < $invoiced * self::THIN_MARGIN ? 'warning' : 'ok';
    }

    /** @return array<int,float> project_id => summed value */
    private function keyedSum(PDO $pdo, string $sql): array
    {
        $out = [];
        foreach ($pdo->query($sql)->fetchAll() as $row) {
            $out[(int) $row['k']] = (float) $row['v'];
        }
        return $out;
    }
}
