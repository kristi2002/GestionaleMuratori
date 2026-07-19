<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Database;
use PDO;

/**
 * Labor hours and cost from the Badge di Cantiere register (site_attendance).
 *
 * Hours come from closed shifts (exit_at IS NOT NULL) as entry→exit duration. The
 * per-hour rate is resolved per row: a subcontractor row (subcontractor_id set)
 * uses subcontractors.hourly_rate; a worker row uses users.hourly_rate. A missing
 * rate counts as 0 cost (the hours are still reported), so labor folds into the
 * project P&L (FinancialsService) without changing any figure until rates are set.
 *
 * Read-only. A single current rate is used (no dated history) — see migration 025.
 */
final class LaborCostService
{
    /**
     * SQL fragments shared by every query. `hoursExpr` is fractional hours;
     * `costExpr` multiplies it by the row's resolved rate (0 when unset).
     */
    private const HOURS_EXPR = 'TIMESTAMPDIFF(SECOND, a.entry_at, a.exit_at) / 3600.0';
    private const RATE_EXPR  = 'COALESCE(CASE WHEN a.subcontractor_id IS NOT NULL THEN s.hourly_rate ELSE u.hourly_rate END, 0)';

    /** @return array<int,float> project_id => labor cost (for FinancialsService) */
    public function costByProject(): array
    {
        $sql = 'SELECT a.project_id AS k, SUM((' . self::HOURS_EXPR . ') * ' . self::RATE_EXPR . ') AS v
                FROM site_attendance a
                LEFT JOIN users u ON u.id = a.user_id
                LEFT JOIN subcontractors s ON s.id = a.subcontractor_id
                WHERE a.exit_at IS NOT NULL
                GROUP BY a.project_id';
        $out = [];
        foreach (Database::pdo()->query($sql)->fetchAll() as $row) {
            $out[(int) $row['k']] = (float) $row['v'];
        }
        return $out;
    }

    /** Labor cost for one project (project detail P&L). */
    public function costForProject(int $projectId): float
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(SUM((' . self::HOURS_EXPR . ') * ' . self::RATE_EXPR . '), 0)
             FROM site_attendance a
             LEFT JOIN users u ON u.id = a.user_id
             LEFT JOIN subcontractors s ON s.id = a.subcontractor_id
             WHERE a.exit_at IS NOT NULL AND a.project_id = ?'
        );
        $stmt->execute([$projectId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Report data: per-project totals, per-person totals, and grand totals, plus a
     * flag for whether any rate is configured at all (drives an onboarding hint).
     *
     * @return array{
     *   projects:array<int,array<string,mixed>>,
     *   people:array<int,array<string,mixed>>,
     *   totals:array{hours:float,cost:float},
     *   any_rate:bool
     * }
     */
    public function summary(): array
    {
        $pdo = Database::pdo();

        $projects = $pdo->query(
            'SELECT p.id, p.name, p.status, c.name AS client_name,
                    SUM(' . self::HOURS_EXPR . ') AS hours,
                    SUM((' . self::HOURS_EXPR . ') * ' . self::RATE_EXPR . ') AS cost
             FROM site_attendance a
             JOIN projects p ON p.id = a.project_id
             JOIN clients c ON c.id = p.client_id
             LEFT JOIN users u ON u.id = a.user_id
             LEFT JOIN subcontractors s ON s.id = a.subcontractor_id
             WHERE a.exit_at IS NOT NULL
             GROUP BY p.id, p.name, p.status, c.name
             ORDER BY cost DESC, hours DESC'
        )->fetchAll();

        // Per person: workers keyed by user_id, subcontractor crews by subcontractor_id.
        $people = $pdo->query(
            'SELECT COALESCE(MAX(u.name), MAX(a.person_name)) AS person_name,
                    MAX(a.subcontractor_id IS NOT NULL) AS is_subcontractor,
                    MAX(s.name) AS company_name,
                    MAX(' . self::RATE_EXPR . ') AS rate,
                    SUM(' . self::HOURS_EXPR . ') AS hours,
                    SUM((' . self::HOURS_EXPR . ') * ' . self::RATE_EXPR . ') AS cost
             FROM site_attendance a
             LEFT JOIN users u ON u.id = a.user_id
             LEFT JOIN subcontractors s ON s.id = a.subcontractor_id
             WHERE a.exit_at IS NOT NULL
             GROUP BY a.user_id, a.subcontractor_id
             ORDER BY cost DESC, hours DESC'
        )->fetchAll();

        $totalHours = 0.0;
        $totalCost  = 0.0;
        foreach ($projects as &$p) {
            $p['hours'] = (float) $p['hours'];
            $p['cost']  = (float) $p['cost'];
            $totalHours += $p['hours'];
            $totalCost  += $p['cost'];
        }
        unset($p);
        foreach ($people as &$person) {
            $person['hours']            = (float) $person['hours'];
            $person['cost']             = (float) $person['cost'];
            $person['rate']             = $person['rate'] !== null ? (float) $person['rate'] : 0.0;
            $person['is_subcontractor'] = (bool) $person['is_subcontractor'];
        }
        unset($person);

        $anyRate = (int) $pdo->query(
            'SELECT (SELECT COUNT(*) FROM users WHERE hourly_rate IS NOT NULL)
                  + (SELECT COUNT(*) FROM subcontractors WHERE hourly_rate IS NOT NULL)'
        )->fetchColumn() > 0;

        return [
            'projects' => $projects,
            'people'   => $people,
            'totals'   => ['hours' => $totalHours, 'cost' => $totalCost],
            'any_rate' => $anyRate,
        ];
    }
}
