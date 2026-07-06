<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Stock locations: the main warehouse (id=1) plus one 'site' location per project.
 * Movements and per-location balances are keyed on these rows.
 */
final class StockLocationModel
{
    public const MAIN_WAREHOUSE_ID = 1;

    /** @return array<int,array<string,mixed>> */
    public function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM stock_locations';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY kind, name';
        return Database::pdo()->query($sql)->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM stock_locations WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** The 'site' location bound to a project, if any. */
    public function findForProject(int $projectId): ?array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM stock_locations WHERE project_id = ? AND kind = 'site' ORDER BY id LIMIT 1"
        );
        $stmt->execute([$projectId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO stock_locations (name, kind, project_id, is_active)
             VALUES (:name, :kind, :project_id, :is_active)'
        );
        $stmt->execute([
            ':name'       => $data['name'],
            ':kind'       => $data['kind'] ?? 'site',
            ':project_id' => $data['project_id'] ?? null,
            ':is_active'  => ($data['is_active'] ?? true) ? 1 : 0,
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /**
     * Ensure a project has a site location, creating it if missing. Returns its id.
     * Idempotent: a project keeps a single site location for its lifetime.
     */
    public function ensureForProject(int $projectId, string $projectName): int
    {
        $existing = $this->findForProject($projectId);
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        return $this->create([
            'name'       => 'Cantiere: ' . $projectName,
            'kind'       => 'site',
            'project_id' => $projectId,
            'is_active'  => true,
        ]);
    }
}
