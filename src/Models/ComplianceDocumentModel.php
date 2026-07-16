<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Scadenzario Sicurezza (D.Lgs. 81/2008, DURC, Patente a Crediti): expiry tracking
 * for mandatory safety/compliance documents. Polymorphic subject
 * (worker/company/subcontractor/project); the dashboard surfaces items expiring
 * within 30 days (or already expired) so nothing lapses unnoticed.
 */
final class ComplianceDocumentModel
{
    /** SELECT with the polymorphic subject name resolved in one query. */
    private const SELECT = "SELECT cd.*,
            CASE cd.subject_type
                WHEN 'worker'        THEN uw.name
                WHEN 'subcontractor' THEN sc.name
                WHEN 'project'       THEN pr.name
                ELSE NULL
            END AS subject_name
        FROM compliance_documents cd
        LEFT JOIN users uw          ON cd.subject_type = 'worker'        AND uw.id = cd.subject_id
        LEFT JOIN subcontractors sc ON cd.subject_type = 'subcontractor' AND sc.id = cd.subject_id
        LEFT JOIN projects pr       ON cd.subject_type = 'project'       AND pr.id = cd.subject_id";

    /**
     * @param array{subject_type?:string,doc_type?:string,expiring?:bool} $filters
     * @return array<int,array<string,mixed>>
     */
    public function all(array $filters = []): array
    {
        $sql    = self::SELECT . ' WHERE 1 = 1';
        $params = [];

        if (!empty($filters['subject_type'])) {
            $sql     .= ' AND cd.subject_type = ?';
            $params[] = $filters['subject_type'];
        }
        if (!empty($filters['doc_type'])) {
            $sql     .= ' AND cd.doc_type = ?';
            $params[] = $filters['doc_type'];
        }
        if (!empty($filters['expiring'])) {
            $sql .= ' AND cd.expiry_date IS NOT NULL AND cd.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
        }
        $sql .= ' ORDER BY cd.expiry_date IS NULL, cd.expiry_date ASC, cd.id DESC';

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(self::SELECT . ' WHERE cd.id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Documents expiring within $days days (or already expired), soonest first.
     * Drives the compliance dashboard widget.
     *
     * @return array<int,array<string,mixed>>
     */
    /**
     * Worst compliance status per subject of a type (for DURC/document gating):
     * 'expired' if any doc is past its expiry, else 'expiring' within $days, else
     * 'ok'. Subjects with no dated documents are simply absent from the map.
     *
     * @return array<int,string> subject_id => 'expired'|'expiring'|'ok'
     */
    public function statusForSubjects(string $subjectType, int $days = 30): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT subject_id,
                    SUM(expiry_date IS NOT NULL AND expiry_date < CURDATE()) AS expired,
                    SUM(expiry_date IS NOT NULL AND expiry_date >= CURDATE()
                        AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL " . (int) $days . " DAY)) AS expiring
             FROM compliance_documents
             WHERE subject_type = ? AND subject_id IS NOT NULL
             GROUP BY subject_id"
        );
        $stmt->execute([$subjectType]);

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $id = (int) $row['subject_id'];
            if ((int) $row['expired'] > 0) {
                $out[$id] = 'expired';
            } elseif ((int) $row['expiring'] > 0) {
                $out[$id] = 'expiring';
            } else {
                $out[$id] = 'ok';
            }
        }
        return $out;
    }

    /**
     * Global bucket counts for the compliance overview KPIs / action banner.
     * Buckets are mutually exclusive so they sum to the full document count:
     *   expired  — past its expiry_date
     *   exp30    — expiring within 30 days (not yet expired)
     *   exp90    — expiring in 31..90 days
     *   valid    — expiring beyond 90 days, or with no expiry date (never lapses)
     *
     * @return array{expired:int,exp30:int,exp90:int,valid:int}
     */
    public function bucketCounts(): array
    {
        $stmt = Database::pdo()->query(
            'SELECT
                SUM(expiry_date IS NOT NULL AND expiry_date < CURDATE()) AS expired,
                SUM(expiry_date IS NOT NULL AND expiry_date >= CURDATE()
                    AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)) AS exp30,
                SUM(expiry_date IS NOT NULL AND expiry_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                    AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY)) AS exp90,
                SUM(expiry_date IS NULL OR expiry_date > DATE_ADD(CURDATE(), INTERVAL 90 DAY)) AS valid
             FROM compliance_documents'
        );
        $row = $stmt->fetch() ?: [];

        return [
            'expired' => (int) ($row['expired'] ?? 0),
            'exp30'   => (int) ($row['exp30'] ?? 0),
            'exp90'   => (int) ($row['exp90'] ?? 0),
            'valid'   => (int) ($row['valid'] ?? 0),
        ];
    }

    public function expiringSoon(int $days = 30): array
    {
        $stmt = Database::pdo()->prepare(
            self::SELECT . ' WHERE cd.expiry_date IS NOT NULL
                             AND cd.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ' . (int) $days . ' DAY)
                    ORDER BY cd.expiry_date ASC'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO compliance_documents
                (subject_type, subject_id, doc_type, reference, issue_date, expiry_date, credits, notes, created_by)
             VALUES
                (:subject_type, :subject_id, :doc_type, :reference, :issue_date, :expiry_date, :credits, :notes, :created_by)'
        );
        $stmt->execute([
            ':subject_type' => $data['subject_type'],
            ':subject_id'   => $data['subject_id'],
            ':doc_type'     => $data['doc_type'],
            ':reference'    => $data['reference'],
            ':issue_date'   => $data['issue_date'],
            ':expiry_date'  => $data['expiry_date'],
            ':credits'      => $data['credits'],
            ':notes'        => $data['notes'],
            ':created_by'   => $data['created_by'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE compliance_documents SET subject_type = :subject_type, subject_id = :subject_id,
                doc_type = :doc_type, reference = :reference, issue_date = :issue_date,
                expiry_date = :expiry_date, credits = :credits, notes = :notes
             WHERE id = :id'
        );
        return $stmt->execute([
            ':subject_type' => $data['subject_type'],
            ':subject_id'   => $data['subject_id'],
            ':doc_type'     => $data['doc_type'],
            ':reference'    => $data['reference'],
            ':issue_date'   => $data['issue_date'],
            ':expiry_date'  => $data['expiry_date'],
            ':credits'      => $data['credits'],
            ':notes'        => $data['notes'],
            ':id'           => $id,
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM compliance_documents WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Delete every compliance document attached to a polymorphic subject. The
     * subject_type/subject_id pair carries no foreign key (it can point at a
     * worker, company, subcontractor or project), so callers must clean up here
     * when the subject itself is deleted — otherwise the Scadenzario keeps orphan
     * rows whose subject no longer resolves.
     */
    public function deleteForSubject(string $subjectType, int $subjectId): int
    {
        $stmt = Database::pdo()->prepare(
            'DELETE FROM compliance_documents WHERE subject_type = ? AND subject_id = ?'
        );
        $stmt->execute([$subjectType, $subjectId]);
        return $stmt->rowCount();
    }
}
