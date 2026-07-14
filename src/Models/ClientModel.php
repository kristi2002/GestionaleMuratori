<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class ClientModel
{
    /** @return array<int,array<string,mixed>> */
    public function all(string $search = '', ?int $limit = null, int $offset = 0): array
    {
        [$where, $params] = $this->filterSql($search);
        // Per-client project count and total invoiced amount (real KPI data for
        // the card grid). Correlated subqueries keep the single-statement shape.
        $sql = 'SELECT clients.*,
                (SELECT COUNT(*) FROM projects p WHERE p.client_id = clients.id) AS project_count,
                (SELECT COALESCE(SUM(pi.amount), 0) FROM project_invoices pi
                    JOIN projects p2 ON p2.id = pi.project_id
                    WHERE p2.client_id = clients.id) AS invoiced_total
            FROM clients' . $where . ' ORDER BY name';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . max(0, $offset);
        }
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Row count for the same search (drives pagination). */
    public function count(string $search = ''): int
    {
        [$where, $params] = $this->filterSql($search);
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM clients' . $where);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return array{0:string,1:array<int,mixed>} */
    private function filterSql(string $search): array
    {
        if ($search === '') {
            return ['', []];
        }
        $like = '%' . $search . '%';
        return [' WHERE name LIKE ? OR vat_or_tax_id LIKE ?', [$like, $like]];
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM clients WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO clients (name, vat_or_tax_id, email, phone, address, notes)
             VALUES (:name, :vat, :email, :phone, :address, :notes)'
        );
        $stmt->execute([
            ':name'    => $data['name'],
            ':vat'     => $data['vat_or_tax_id'],
            ':email'   => $data['email'],
            ':phone'   => $data['phone'],
            ':address' => $data['address'],
            ':notes'   => $data['notes'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE clients SET name = :name, vat_or_tax_id = :vat, email = :email,
                phone = :phone, address = :address, notes = :notes
             WHERE id = :id'
        );
        return $stmt->execute([
            ':name'    => $data['name'],
            ':vat'     => $data['vat_or_tax_id'],
            ':email'   => $data['email'],
            ':phone'   => $data['phone'],
            ':address' => $data['address'],
            ':notes'   => $data['notes'],
            ':id'      => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM clients WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /** Total number of projects across every client (KPI header). */
    public function totalProjects(): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM projects');
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /** Sum of all invoiced amounts across every project (KPI header). */
    public function totalInvoiced(): float
    {
        $stmt = Database::pdo()->prepare('SELECT COALESCE(SUM(amount), 0) FROM project_invoices');
        $stmt->execute();
        return (float) $stmt->fetchColumn();
    }

    /** Number of projects linked to this client — used to warn before a cascading delete. */
    public function countProjects(int $id): int
    {
        $stmt = Database::pdo()->prepare('SELECT COUNT(*) FROM projects WHERE client_id = ?');
        $stmt->execute([$id]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Real financial + project aggregates for the client profile page.
     * @return array{invoiced_total:float,paid_total:float,outstanding_total:float,
     *   projects_total:int,projects_active:int,last_payment_date:?string,next_deadline:?string}
     */
    public function profileStats(int $id): array
    {
        $pdo = Database::pdo();

        // Invoiced / paid / outstanding, joined through the client's projects.
        $fin = $pdo->prepare(
            "SELECT COALESCE(SUM(pi.amount), 0) AS invoiced_total,
                    COALESCE(SUM(CASE WHEN pi.status = 'paid'   THEN pi.amount ELSE 0 END), 0) AS paid_total,
                    COALESCE(SUM(CASE WHEN pi.status = 'issued' THEN pi.amount ELSE 0 END), 0) AS outstanding_total,
                    MAX(CASE WHEN pi.status = 'paid' THEN pi.issue_date END) AS last_payment_date
             FROM project_invoices pi
             JOIN projects p ON p.id = pi.project_id
             WHERE p.client_id = ?"
        );
        $fin->execute([$id]);
        $row = $fin->fetch() ?: [];

        $proj = $pdo->prepare(
            "SELECT COUNT(*) AS projects_total,
                    COALESCE(SUM(status = 'active'), 0) AS projects_active,
                    MIN(CASE WHEN status = 'active' AND end_date >= CURDATE() THEN end_date END) AS next_deadline
             FROM projects WHERE client_id = ?"
        );
        $proj->execute([$id]);
        $prow = $proj->fetch() ?: [];

        return [
            'invoiced_total'    => (float) ($row['invoiced_total'] ?? 0),
            'paid_total'        => (float) ($row['paid_total'] ?? 0),
            'outstanding_total' => (float) ($row['outstanding_total'] ?? 0),
            'projects_total'    => (int) ($prow['projects_total'] ?? 0),
            'projects_active'   => (int) ($prow['projects_active'] ?? 0),
            'last_payment_date' => $row['last_payment_date'] ?? null,
            'next_deadline'     => $prow['next_deadline'] ?? null,
        ];
    }

    /**
     * The client's projects with real intervention-completion progress.
     * @return array<int,array<string,mixed>>
     */
    public function projectsForProfile(int $id): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM interventions i WHERE i.project_id = p.id) AS interv_total,
                    (SELECT COUNT(*) FROM interventions i WHERE i.project_id = p.id AND i.status = 'completed') AS interv_done
             FROM projects p
             WHERE p.client_id = ?
             ORDER BY FIELD(p.status, 'active', 'on_hold', 'closed'), p.start_date DESC"
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    /**
     * The client's invoices (most recent first), with the project name.
     * @return array<int,array<string,mixed>>
     */
    public function invoicesForProfile(int $id, int $limit = 20): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT pi.*, p.name AS project_name
             FROM project_invoices pi
             JOIN projects p ON p.id = pi.project_id
             WHERE p.client_id = ?
             ORDER BY pi.issue_date DESC, pi.id DESC
             LIMIT ' . (int) $limit
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    /**
     * Invoiced amount per calendar month for the last 12 months (chart series).
     * @return array<int,array{label:string,value:int}>
     */
    public function monthlyInvoiced(int $id): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT DATE_FORMAT(pi.issue_date, '%Y-%m') AS ym, COALESCE(SUM(pi.amount), 0) AS total
             FROM project_invoices pi
             JOIN projects p ON p.id = pi.project_id
             WHERE p.client_id = ? AND pi.issue_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 11 MONTH)
             GROUP BY ym"
        );
        $stmt->execute([$id]);
        $byMonth = [];
        foreach ($stmt->fetchAll() as $r) {
            $byMonth[(string) $r['ym']] = (int) round((float) $r['total']);
        }

        // Zero-fill the 12-month window so quiet months read as 0, not a gap.
        $months = ['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu', 'Lug', 'Ago', 'Set', 'Ott', 'Nov', 'Dic'];
        $out = [];
        $cursor = new \DateTimeImmutable('first day of this month');
        $cursor = $cursor->modify('-11 months');
        for ($i = 0; $i < 12; $i++) {
            $ym = $cursor->format('Y-m');
            $out[] = ['label' => $months[(int) $cursor->format('n') - 1], 'value' => $byMonth[$ym] ?? 0];
            $cursor = $cursor->modify('+1 month');
        }
        return $out;
    }

    /**
     * Recent real activity for the client: invoices, quotes and project starts,
     * newest first. Each row: {type, ev_date, ref, title, amount, status}.
     * @return array<int,array<string,mixed>>
     */
    public function activityTimeline(int $id, int $limit = 8): array
    {
        // Distinct placeholders per UNION branch: the app's PDO runs with
        // emulation off, which forbids reusing one named param across the query.
        $stmt = Database::pdo()->prepare(
            "SELECT * FROM (
                SELECT 'invoice' AS type, pi.issue_date AS ev_date, pi.number AS ref,
                       p.name AS title, pi.amount AS amount, pi.status AS status
                  FROM project_invoices pi JOIN projects p ON p.id = pi.project_id
                 WHERE p.client_id = :id1
                UNION ALL
                SELECT 'quote' AS type, q.quote_date AS ev_date, q.number AS ref,
                       q.title AS title, NULL AS amount, q.status AS status
                  FROM quotes q WHERE q.client_id = :id2
                UNION ALL
                SELECT 'project' AS type, p.start_date AS ev_date, NULL AS ref,
                       p.name AS title, NULL AS amount, p.status AS status
                  FROM projects p WHERE p.client_id = :id3
             ) events
             ORDER BY ev_date DESC, type
             LIMIT " . (int) $limit
        );
        $stmt->execute([':id1' => $id, ':id2' => $id, ':id3' => $id]);
        return $stmt->fetchAll();
    }
}
