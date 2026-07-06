<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * S.A.L. — Stato Avanzamento Lavori: a numbered work-progress statement per project,
 * certifying the value of work done in a period for staged invoicing and DL sign-off.
 * Lifecycle draft → issued (locked PDF generated) → signed (terminal). Only a draft
 * is editable; issuing freezes the priced line items.
 */
final class SalDocumentModel
{
    /** Next per-project S.A.L. number (1-based, gap-free within a project). */
    public function nextNumber(int $projectId): int
    {
        $stmt = Database::pdo()->prepare(
            'SELECT COALESCE(MAX(number), 0) + 1 FROM sal_documents WHERE project_id = ?'
        );
        $stmt->execute([$projectId]);
        return (int) $stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT s.*, p.name AS project_name, p.location AS project_location,
                    c.name AS client_name, c.vat_or_tax_id AS client_vat, c.address AS client_address,
                    u.name AS created_by_name
             FROM sal_documents s
             JOIN projects p ON p.id = s.project_id
             JOIN clients c ON c.id = p.client_id
             JOIN users u ON u.id = s.created_by
             WHERE s.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int,array<string,mixed>> */
    public function forProject(int $projectId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM sal_documents WHERE project_id = ? ORDER BY number DESC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO sal_documents (project_id, number, period_from, period_to, description, created_by)
             VALUES (:project_id, :number, :period_from, :period_to, :description, :created_by)'
        );
        $stmt->execute([
            ':project_id'  => $data['project_id'],
            ':number'      => $data['number'],
            ':period_from' => $data['period_from'] ?? null,
            ':period_to'   => $data['period_to'] ?? null,
            ':description' => $data['description'] ?? null,
            ':created_by'  => $data['created_by'],
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    /** Edit header fields — only while status = draft (enforced in WHERE). */
    public function updateHeader(int $id, array $data): bool
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE sal_documents SET period_from = :period_from, period_to = :period_to,
                description = :description
             WHERE id = :id AND status = 'draft'"
        );
        return $stmt->execute([
            ':period_from' => $data['period_from'] ?? null,
            ':period_to'   => $data['period_to'] ?? null,
            ':description' => $data['description'] ?? null,
            ':id'          => $id,
        ]);
    }

    /** Recompute the document total from its line items. Returns the new total. */
    public function recomputeAmount(int $id): string
    {
        $sum = Database::pdo()->prepare('SELECT COALESCE(SUM(amount), 0) FROM sal_lines WHERE sal_id = ?');
        $sum->execute([$id]);
        $total = (string) $sum->fetchColumn();

        $upd = Database::pdo()->prepare('UPDATE sal_documents SET amount = ? WHERE id = ?');
        $upd->execute([$total, $id]);
        return $total;
    }

    /** draft → issued, storing the generated locked PDF path. */
    public function markIssued(int $id, string $pdfPath): bool
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE sal_documents SET status = 'issued', pdf_path = :pdf, issued_at = NOW()
             WHERE id = :id AND status = 'draft'"
        );
        return $stmt->execute([':pdf' => $pdfPath, ':id' => $id]);
    }

    /** issued → signed, storing the DL signature image path. */
    public function markSigned(int $id, string $signaturePath): bool
    {
        $stmt = Database::pdo()->prepare(
            "UPDATE sal_documents SET status = 'signed', signature_path = :sig, signed_at = NOW()
             WHERE id = :id AND status = 'issued'"
        );
        return $stmt->execute([':sig' => $signaturePath, ':id' => $id]);
    }
}
