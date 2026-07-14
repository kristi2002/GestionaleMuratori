<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Database;
use DateInterval;
use DateTimeImmutable;
use PDO;

/**
 * Read-only aggregated figures for the admin statistics dashboard.
 * Plain grouped COUNT/SUM over the operational tables — no writes, no locks.
 * All SQL here is static (no user input), so identifiers are safe to inline.
 */
final class StatisticsService
{
    /** @return array<string,mixed> */
    public function all(): array
    {
        $pdo = Database::pdo();

        return [
            'kpi'                     => $this->kpi($pdo),
            'projects_by_status'      => $this->keyedCount($pdo, "SELECT status AS k, COUNT(*) AS c FROM projects GROUP BY status"),
            'interventions_by_status' => $this->keyedCount($pdo, "SELECT status AS k, COUNT(*) AS c FROM interventions GROUP BY status"),
            'quotes_by_status'        => $this->keyedCount($pdo, "SELECT status AS k, COUNT(*) AS c FROM quotes GROUP BY status"),
            'invoices_by_status'      => $this->invoicesByStatus($pdo),
            'expenses_by_category'    => $this->expensesByCategory($pdo),
            'top_clients'             => $this->topClients($pdo),
            'interventions_by_month'  => $this->interventionsByMonth($pdo),
        ];
    }

    /** @return array<string,float|int> */
    private function kpi(PDO $pdo): array
    {
        return [
            'total_projects'      => (int) $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn(),
            'total_interventions' => (int) $pdo->query("SELECT COUNT(*) FROM interventions")->fetchColumn(),
            'total_workers'       => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'worker' AND is_active = 1")->fetchColumn(),
            'active_projects'     => (int) $pdo->query("SELECT COUNT(*) FROM projects WHERE status='active'")->fetchColumn(),
            'interventions_month' => (int) $pdo->query(
                "SELECT COUNT(*) FROM interventions
                 WHERE scheduled_date IS NOT NULL
                   AND YEAR(scheduled_date) = YEAR(CURDATE())
                   AND MONTH(scheduled_date) = MONTH(CURDATE())"
            )->fetchColumn(),
            'low_stock'           => (int) $pdo->query(
                "SELECT COUNT(*) FROM warehouse_items
                 WHERE is_active = 1 AND reorder_level > 0 AND qty_in_stock <= reorder_level"
            )->fetchColumn(),
            'revenue_paid'        => (float) $pdo->query(
                "SELECT COALESCE(SUM(amount), 0) FROM project_invoices WHERE status = 'paid'"
            )->fetchColumn(),
        ];
    }

    /** @return array<string,int> ENUM value => count */
    private function keyedCount(PDO $pdo, string $sql): array
    {
        $out = [];
        foreach ($pdo->query($sql)->fetchAll() as $row) {
            $out[(string) $row['k']] = (int) $row['c'];
        }
        return $out;
    }

    /** @return array<string,array{count:int,total:float}> */
    private function invoicesByStatus(PDO $pdo): array
    {
        $out = [];
        $sql = "SELECT status AS k, COUNT(*) AS c, COALESCE(SUM(amount), 0) AS total
                FROM project_invoices GROUP BY status";
        foreach ($pdo->query($sql)->fetchAll() as $row) {
            $out[(string) $row['k']] = ['count' => (int) $row['c'], 'total' => (float) $row['total']];
        }
        return $out;
    }

    /** @return array<string,float> category => summed amount, desc */
    private function expensesByCategory(PDO $pdo): array
    {
        $out = [];
        $sql = "SELECT category AS k, COALESCE(SUM(amount), 0) AS total
                FROM expenses GROUP BY category ORDER BY total DESC";
        foreach ($pdo->query($sql)->fetchAll() as $row) {
            $out[(string) $row['k']] = (float) $row['total'];
        }
        return $out;
    }

    /** @return array<int,array{name:string,count:int}> top 6 clients by project count */
    private function topClients(PDO $pdo): array
    {
        $sql = "SELECT cl.name AS name, COUNT(p.id) AS cnt
                FROM clients cl
                LEFT JOIN projects p ON p.client_id = cl.id
                GROUP BY cl.id, cl.name
                ORDER BY cnt DESC, cl.name
                LIMIT 6";
        return array_map(
            static fn (array $r): array => ['name' => (string) $r['name'], 'count' => (int) $r['cnt']],
            $pdo->query($sql)->fetchAll()
        );
    }

    /**
     * Interventions scheduled per calendar month over the last 6 months
     * (including the current one), zero-filled.
     *
     * @return array<int,array{label:string,value:int}>
     */
    private function interventionsByMonth(PDO $pdo): array
    {
        $sql = "SELECT DATE_FORMAT(scheduled_date, '%Y-%m') AS ym, COUNT(*) AS c
                FROM interventions
                WHERE scheduled_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
                GROUP BY ym";
        $counts = [];
        foreach ($pdo->query($sql)->fetchAll() as $row) {
            $counts[(string) $row['ym']] = (int) $row['c'];
        }

        $out    = [];
        $cursor = (new DateTimeImmutable('first day of this month'))->sub(new DateInterval('P5M'));
        for ($i = 0; $i < 6; $i++) {
            $out[]  = ['label' => $cursor->format('m/y'), 'value' => $counts[$cursor->format('Y-m')] ?? 0];
            $cursor = $cursor->add(new DateInterval('P1M'));
        }
        return $out;
    }
}
