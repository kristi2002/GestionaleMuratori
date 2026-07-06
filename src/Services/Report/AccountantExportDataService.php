<?php
declare(strict_types=1);

namespace App\Services\Report;

use App\Support\Database;

/**
 * Aggregates a month of costs for the accountant export (Esportazione per il
 * Commercialista): material consumption valued at warehouse_items.unit_cost, plus
 * worker hours derived from the Badge di Cantiere attendance. Read-only.
 */
final class AccountantExportDataService
{
    /**
     * @return array{
     *   from:string, to:string,
     *   materials:array<int,array<string,mixed>>,
     *   labor:array<int,array<string,mixed>>,
     *   projects:array<int,array<string,mixed>>,
     *   totals:array{material_cost:float,hours:float}
     * }
     */
    public function build(string $from, string $toExclusive): array
    {
        $pdo = Database::pdo();

        // Material consumption (ledger 'out' rows) valued at unit_cost, per item.
        $matStmt = $pdo->prepare(
            "SELECT w.name AS item_name, w.unit, w.unit_cost,
                    SUM(m.qty) AS total_qty,
                    SUM(m.qty * COALESCE(w.unit_cost, 0)) AS total_cost
             FROM stock_movements m
             JOIN warehouse_items w ON w.id = m.item_id
             WHERE m.type = 'out' AND m.created_at >= ? AND m.created_at < ?
             GROUP BY w.id, w.name, w.unit, w.unit_cost
             ORDER BY w.name"
        );
        $matStmt->execute([$from, $toExclusive]);
        $materials = $matStmt->fetchAll();

        // Worker hours from closed attendance windows.
        $laborStmt = $pdo->prepare(
            'SELECT u.name AS worker_name,
                    SUM(TIMESTAMPDIFF(MINUTE, a.entry_at, a.exit_at)) / 60 AS hours,
                    COUNT(*) AS shifts
             FROM site_attendance a
             JOIN users u ON u.id = a.user_id
             WHERE a.exit_at IS NOT NULL AND a.entry_at >= ? AND a.entry_at < ?
             GROUP BY u.id, u.name
             ORDER BY u.name'
        );
        $laborStmt->execute([$from, $toExclusive]);
        $labor = $laborStmt->fetchAll();

        // Material cost per project (cantiere) — the accountant's cost-centre view.
        $projStmt = $pdo->prepare(
            "SELECT p.name AS project_name, c.name AS client_name,
                    SUM(m.qty * COALESCE(w.unit_cost, 0)) AS material_cost
             FROM stock_movements m
             JOIN warehouse_items w ON w.id = m.item_id
             JOIN interventions i ON i.id = m.intervention_id
             JOIN projects p ON p.id = i.project_id
             JOIN clients c ON c.id = p.client_id
             WHERE m.type = 'out' AND m.created_at >= ? AND m.created_at < ?
             GROUP BY p.id, p.name, c.name
             ORDER BY p.name"
        );
        $projStmt->execute([$from, $toExclusive]);
        $projects = $projStmt->fetchAll();

        $totalCost  = array_sum(array_map(static fn ($m) => (float) $m['total_cost'], $materials));
        $totalHours = array_sum(array_map(static fn ($l) => (float) $l['hours'], $labor));

        return [
            'from'      => $from,
            'to'        => (new \DateTimeImmutable($toExclusive))->modify('-1 day')->format('Y-m-d'),
            'materials' => $materials,
            'labor'     => $labor,
            'projects'  => $projects,
            'totals'    => ['material_cost' => $totalCost, 'hours' => $totalHours],
        ];
    }
}
