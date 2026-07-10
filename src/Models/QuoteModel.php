<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class QuoteModel
{
    /**
     * @param array{search?:string,status?:string,client_id?:int} $filters
     * @return array<int,array<string,mixed>> Quotes with client/project names and computed subtotal.
     */
    public function all(array $filters = [], ?int $limit = null, int $offset = 0): array
    {
        [$where, $params] = $this->filterSql($filters);
        $sql = 'SELECT q.*, c.name AS client_name, p.name AS project_name,
                       (SELECT COALESCE(SUM(l.qty * l.unit_price), 0)
                          FROM quote_lines l WHERE l.quote_id = q.id) AS subtotal
                FROM quotes q
                JOIN clients c ON c.id = q.client_id
                LEFT JOIN projects p ON p.id = q.project_id'
            . $where
            . ' ORDER BY q.quote_date DESC, q.id DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit . ' OFFSET ' . max(0, $offset);
        }

        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Row count for the same filters (drives pagination). */
    public function count(array $filters = []): int
    {
        [$where, $params] = $this->filterSql($filters);
        $stmt = Database::pdo()->prepare(
            'SELECT COUNT(*) FROM quotes q JOIN clients c ON c.id = q.client_id' . $where
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return array{0:string,1:array<int,mixed>} Shared WHERE builder for all()/count(). */
    private function filterSql(array $filters): array
    {
        $sql    = ' WHERE 1 = 1';
        $params = [];

        if (!empty($filters['search'])) {
            $sql     .= ' AND (q.number LIKE ? OR q.title LIKE ? OR c.name LIKE ?)';
            $like     = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['status'])) {
            $sql     .= ' AND q.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['client_id'])) {
            $sql     .= ' AND q.client_id = ?';
            $params[] = (int) $filters['client_id'];
        }
        return [$sql, $params];
    }

    /**
     * Quotes visible to a client in their portal: everything the admin has sent or
     * decided (never 'draft'). Newest first, with the computed subtotal.
     *
     * @return array<int,array<string,mixed>>
     */
    public function forClient(int $clientId): array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT q.*, p.name AS project_name,
                    (SELECT COALESCE(SUM(l.qty * l.unit_price), 0) FROM quote_lines l WHERE l.quote_id = q.id) AS subtotal
             FROM quotes q
             LEFT JOIN projects p ON p.id = q.project_id
             WHERE q.client_id = ? AND q.status <> 'draft'
             ORDER BY q.quote_date DESC, q.id DESC"
        );
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }

    /** A single quote owned by this client and not a draft (portal ownership guard). */
    public function findForClient(int $id, int $clientId): ?array
    {
        $stmt = Database::pdo()->prepare(
            "SELECT q.*, p.name AS project_name
             FROM quotes q
             LEFT JOIN projects p ON p.id = q.project_id
             WHERE q.id = ? AND q.client_id = ? AND q.status <> 'draft' LIMIT 1"
        );
        $stmt->execute([$id, $clientId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Client accept/reject. Only a 'sent' quote owned by the client may transition,
     * and only to 'accepted' or 'rejected'. Returns true when a row was updated
     * (guards against double-decisions and expired quotes via the WHERE clause).
     */
    public function setClientDecision(int $id, int $clientId, string $status): bool
    {
        if (!in_array($status, ['accepted', 'rejected'], true)) {
            return false;
        }
        $stmt = Database::pdo()->prepare(
            "UPDATE quotes SET status = ? WHERE id = ? AND client_id = ? AND status = 'sent'"
        );
        $stmt->execute([$status, $id, $clientId]);
        return $stmt->rowCount() > 0;
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT q.*, c.name AS client_name, c.address AS client_address,
                    c.vat_or_tax_id AS client_vat, c.email AS client_email,
                    p.name AS project_name, u.name AS created_by_name
             FROM quotes q
             JOIN clients c ON c.id = q.client_id
             LEFT JOIN projects p ON p.id = q.project_id
             JOIN users u ON u.id = q.created_by
             WHERE q.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<int,array<string,mixed>> Line items in display order. */
    public function lines(int $quoteId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM quote_lines WHERE quote_id = ? ORDER BY sort_order, id'
        );
        $stmt->execute([$quoteId]);
        return $stmt->fetchAll();
    }

    /** @param array<int,array<string,mixed>> $lines */
    public function create(array $data, array $lines): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO quotes (client_id, project_id, number, title, quote_date, valid_until, status, vat_rate, notes, created_by)
                 VALUES (:client_id, :project_id, :number, :title, :quote_date, :valid_until, :status, :vat_rate, :notes, :created_by)'
            );
            $stmt->execute([
                ':client_id'   => $data['client_id'],
                ':project_id'  => $data['project_id'],
                ':number'      => $data['number'],
                ':title'       => $data['title'],
                ':quote_date'  => $data['quote_date'],
                ':valid_until' => $data['valid_until'],
                ':status'      => $data['status'],
                ':vat_rate'    => $data['vat_rate'],
                ':notes'       => $data['notes'],
                ':created_by'  => $data['created_by'],
            ]);
            $id = (int) $pdo->lastInsertId();
            $this->insertLines($id, $lines);
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** @param array<int,array<string,mixed>> $lines Replaces all existing lines. */
    public function update(int $id, array $data, array $lines): void
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE quotes SET client_id = :client_id, project_id = :project_id, number = :number,
                    title = :title, quote_date = :quote_date, valid_until = :valid_until,
                    status = :status, vat_rate = :vat_rate, notes = :notes
                 WHERE id = :id'
            );
            $stmt->execute([
                ':client_id'   => $data['client_id'],
                ':project_id'  => $data['project_id'],
                ':number'      => $data['number'],
                ':title'       => $data['title'],
                ':quote_date'  => $data['quote_date'],
                ':valid_until' => $data['valid_until'],
                ':status'      => $data['status'],
                ':vat_rate'    => $data['vat_rate'],
                ':notes'       => $data['notes'],
                ':id'          => $id,
            ]);
            $pdo->prepare('DELETE FROM quote_lines WHERE quote_id = ?')->execute([$id]);
            $this->insertLines($id, $lines);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM quotes WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Suggested next number for the create form, e.g. "2026/003".
     * Based on the highest suffix already issued this year (not a row count),
     * so deleting a quote can never cause a number to be re-used.
     */
    public function nextNumberSuggestion(): string
    {
        $stmt = Database::pdo()->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(number, '/', -1) AS UNSIGNED))
             FROM quotes WHERE number LIKE CONCAT(YEAR(CURDATE()), '/%')"
        );
        $stmt->execute();
        return date('Y') . '/' . str_pad((string) ((int) $stmt->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
    }

    /** @param array<int,array<string,mixed>> $lines */
    private function insertLines(int $quoteId, array $lines): void
    {
        $insert = Database::pdo()->prepare(
            'INSERT INTO quote_lines (quote_id, description, qty, unit, unit_price, sort_order)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach (array_values($lines) as $i => $line) {
            $insert->execute([
                $quoteId,
                $line['description'],
                $line['qty'],
                $line['unit'],
                $line['unit_price'],
                $i,
            ]);
        }
    }
}
