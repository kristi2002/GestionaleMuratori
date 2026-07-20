<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class ProjectInvoiceModel
{
    /**
     * An unpaid (issued) invoice is treated as overdue once its issue_date is
     * older than this many days — standard 30-day payment terms, since the
     * schema has no explicit due_date column.
     */
    private const OVERDUE_DAYS = 30;


    /** @return array<int,array<string,mixed>> Invoices of a project, newest first. */
    public function forProject(int $projectId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT i.*, u.name AS created_by_name
             FROM project_invoices i JOIN users u ON u.id = i.created_by
             WHERE i.project_id = ?
             ORDER BY i.issue_date DESC, i.id DESC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    /**
     * @param array{search?:string,status?:string,project_id?:int} $filters
     * @return array<int,array<string,mixed>> All invoices with project/client names ("Fatture" list page).
     */
    public function all(array $filters = [], ?int $limit = null, int $offset = 0): array
    {
        [$where, $params] = $this->filterSql($filters);
        $sql = 'SELECT i.*, p.name AS project_name, c.name AS client_name
                FROM project_invoices i
                JOIN projects p ON p.id = i.project_id
                JOIN clients c ON c.id = p.client_id'
            . $where
            . ' ORDER BY i.issue_date DESC, i.id DESC';
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
            'SELECT COUNT(*) FROM project_invoices i
             JOIN projects p ON p.id = i.project_id
             JOIN clients c ON c.id = p.client_id' . $where
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
            $sql     .= ' AND (i.number LIKE ? OR p.name LIKE ? OR c.name LIKE ?)';
            $like     = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['status'])) {
            $sql     .= ' AND i.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['project_id'])) {
            $sql     .= ' AND i.project_id = ?';
            $params[] = (int) $filters['project_id'];
        }
        return [$sql, $params];
    }

    /**
     * Invoice counts grouped by status across the whole table — drives the
     * pill-filter badges on the "Fatture" list page.
     * @return array<string,int> e.g. ['draft'=>2,'issued'=>5,'paid'=>9]
     */
    public function statusCounts(): array
    {
        $stmt = Database::pdo()->query('SELECT status, COUNT(*) AS n FROM project_invoices GROUP BY status');
        $out  = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['status']] = (int) $row['n'];
        }
        return $out;
    }

    /**
     * Header KPI aggregates (all real data, whole table): amount issued this
     * month, cashed-in (paid), still outstanding (issued) and overdue.
     * @return array<string,string> Numeric strings (SUM/COUNT) keyed by metric.
     */
    public function summary(): array
    {
        $stmt = Database::pdo()->query(
            "SELECT
                COALESCE(SUM(CASE WHEN status IN ('issued','paid')
                    AND YEAR(issue_date) = YEAR(CURDATE())
                    AND MONTH(issue_date) = MONTH(CURDATE()) THEN amount END), 0) AS issued_month_total,
                SUM(CASE WHEN status IN ('issued','paid')
                    AND YEAR(issue_date) = YEAR(CURDATE())
                    AND MONTH(issue_date) = MONTH(CURDATE()) THEN 1 ELSE 0 END) AS issued_month_count,
                COALESCE(SUM(CASE WHEN status = 'paid' THEN amount END), 0) AS paid_total,
                SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
                COALESCE(SUM(CASE WHEN status = 'issued' THEN amount END), 0) AS outstanding_total,
                SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) AS outstanding_count,
                COALESCE(SUM(CASE WHEN status = 'issued'
                    AND issue_date < (CURDATE() - INTERVAL " . self::OVERDUE_DAYS . " DAY) THEN amount END), 0) AS overdue_total,
                SUM(CASE WHEN status = 'issued'
                    AND issue_date < (CURDATE() - INTERVAL " . self::OVERDUE_DAYS . " DAY) THEN 1 ELSE 0 END) AS overdue_count
             FROM project_invoices"
        );
        $row = $stmt->fetch();
        return $row ? array_map(static fn ($v): string => (string) $v, $row) : [];
    }

    /** Days after issue_date an unpaid invoice is considered overdue (for the view). */
    public function overdueDays(): int
    {
        return self::OVERDUE_DAYS;
    }

    public function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM project_invoices WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Invoice with project + client details — the printable receipt needs both. */
    public function findWithDetails(int $id): ?array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT i.*, p.name AS project_name, p.location AS project_location,
                    p.client_id AS client_id,
                    c.name AS client_name, c.address AS client_address,
                    c.vat_or_tax_id AS client_vat, c.email AS client_email,
                    u.name AS created_by_name
             FROM project_invoices i
             JOIN projects p ON p.id = i.project_id
             JOIN clients c ON c.id = p.client_id
             JOIN users u ON u.id = i.created_by
             WHERE i.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Fiscal columns (migration 035) shared by the INSERT/UPDATE column list. */
    private const FISCAL_COLS = [
        'cig', 'cup', 'document_type', 'imponibile', 'imposta', 'ritenuta_rate',
        'ritenuta_amount', 'ritenuta_tipo', 'ritenuta_causale', 'bollo',
        'split_payment', 'payment_method', 'payment_iban', 'payment_due',
    ];

    /** @return array<int,array<string,mixed>> Fiscal line items in display order. */
    public function lines(int $invoiceId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM invoice_lines WHERE invoice_id = ? ORDER BY sort_order, id'
        );
        $stmt->execute([$invoiceId]);
        return $stmt->fetchAll();
    }

    /**
     * @param array<int,array<string,mixed>> $lines when non-empty, the invoice is
     *        treated as a fiscal document: totals are computed and cached, and the
     *        lines are stored. When empty, a plain invoice is created (legacy path).
     */
    public function create(array $data, array $lines = []): int
    {
        $data = $this->withFiscalTotals($data, $lines);
        $pdo  = Database::pdo();
        $tx   = $lines !== [] && !$pdo->inTransaction();
        if ($tx) {
            $pdo->beginTransaction();
        }
        try {
            $params = $this->rowParams($data) + [':created_by' => $data['created_by']];
            $cols   = 'project_id, number, issue_date, amount, status, note, created_by, ' . implode(', ', self::FISCAL_COLS);
            $vals   = ':project_id, :number, :issue_date, :amount, :status, :note, :created_by, :' . implode(', :', self::FISCAL_COLS);
            $pdo->prepare("INSERT INTO project_invoices ($cols) VALUES ($vals)")->execute($params);
            $id = (int) $pdo->lastInsertId();
            if ($lines !== []) {
                $this->replaceLines($id, $lines);
            }
            if ($tx) {
                $pdo->commit();
            }
            return $id;
        } catch (\Throwable $e) {
            if ($tx) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @param array<int,array<string,mixed>> $lines empty leaves any existing lines untouched. */
    public function update(int $id, array $data, array $lines = []): bool
    {
        $data = $this->withFiscalTotals($data, $lines);
        $pdo  = Database::pdo();
        $tx   = $lines !== [] && !$pdo->inTransaction();
        if ($tx) {
            $pdo->beginTransaction();
        }
        try {
            $sets = 'project_id = :project_id, number = :number, issue_date = :issue_date,
                     amount = :amount, status = :status, note = :note';
            foreach (self::FISCAL_COLS as $c) {
                $sets .= ", $c = :$c";
            }
            $params = $this->rowParams($data) + [':id' => $id];
            $ok = $pdo->prepare("UPDATE project_invoices SET $sets WHERE id = :id")->execute($params);
            if ($lines !== []) {
                $this->replaceLines($id, $lines);
            }
            if ($tx) {
                $pdo->commit();
            }
            return $ok;
        } catch (\Throwable $e) {
            if ($tx) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Named params for the shared invoice columns, with fiscal defaults. */
    private function rowParams(array $data): array
    {
        $pos = static fn (string $k) => (isset($data[$k]) && (float) $data[$k] > 0) ? $data[$k] : null;
        return [
            ':project_id'       => $data['project_id'],
            ':number'           => $data['number'],
            ':issue_date'       => $data['issue_date'],
            ':amount'           => $data['amount'],
            ':status'           => $data['status'],
            ':note'             => $data['note'] ?? null,
            ':cig'              => $data['cig'] ?? null,
            ':cup'              => $data['cup'] ?? null,
            ':document_type'    => $data['document_type'] ?? 'TD01',
            ':imponibile'       => $data['imponibile'] ?? null,
            ':imposta'          => $data['imposta'] ?? null,
            ':ritenuta_rate'    => $pos('ritenuta_rate'),
            ':ritenuta_amount'  => $data['ritenuta_amount'] ?? null,
            ':ritenuta_tipo'    => $data['ritenuta_tipo'] ?? null,
            ':ritenuta_causale' => $data['ritenuta_causale'] ?? null,
            ':bollo'            => $pos('bollo'),
            ':split_payment'    => !empty($data['split_payment']) ? 1 : 0,
            ':payment_method'   => $data['payment_method'] ?? 'MP05',
            ':payment_iban'     => $data['payment_iban'] ?? null,
            ':payment_due'      => $data['payment_due'] ?? null,
        ];
    }

    /** Compute + cache imponibile/imposta/ritenuta/amount from lines (fiscal path only). */
    private function withFiscalTotals(array $data, array $lines): array
    {
        if ($lines === []) {
            return $data;
        }
        $totals = \App\Support\InvoiceTotals::compute($lines, [
            'ritenuta_rate' => $data['ritenuta_rate'] ?? 0,
            'bollo'         => $data['bollo'] ?? 0,
        ]);
        $data['imponibile']      = number_format($totals['imponibile'], 2, '.', '');
        $data['imposta']         = number_format($totals['imposta'], 2, '.', '');
        $data['ritenuta_amount'] = $totals['ritenuta'] > 0 ? number_format($totals['ritenuta'], 2, '.', '') : null;
        $data['amount']          = number_format($totals['total_document'], 2, '.', '');
        return $data;
    }

    /** @param array<int,array<string,mixed>> $lines Replaces all existing lines. */
    private function replaceLines(int $invoiceId, array $lines): void
    {
        $pdo = Database::pdo();
        $pdo->prepare('DELETE FROM invoice_lines WHERE invoice_id = ?')->execute([$invoiceId]);
        $insert = $pdo->prepare(
            'INSERT INTO invoice_lines (invoice_id, description, qty, unit, unit_price, vat_rate, natura, line_total, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        foreach (array_values($lines) as $i => $line) {
            $insert->execute([
                $invoiceId,
                $line['description'],
                $line['qty'],
                $line['unit'] ?? null,
                $line['unit_price'],
                $line['vat_rate'],
                ($line['natura'] ?? null) !== '' ? ($line['natura'] ?? null) : null,
                \App\Support\InvoiceTotals::lineTotal($line['qty'], $line['unit_price']),
                $i,
            ]);
        }
    }

    public function delete(int $id): bool
    {
        $stmt = Database::pdo()->prepare('DELETE FROM project_invoices WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Suggested next number for the create form, e.g. "2026/003".
     * Based on the highest suffix already issued this year (not a row count),
     * so deleting an invoice can never cause a number to be re-used.
     */
    public function nextNumberSuggestion(): string
    {
        $stmt = Database::pdo()->prepare(
            "SELECT MAX(CAST(SUBSTRING_INDEX(number, '/', -1) AS UNSIGNED))
             FROM project_invoices WHERE number LIKE CONCAT(YEAR(CURDATE()), '/%')"
        );
        $stmt->execute();
        return date('Y') . '/' . str_pad((string) ((int) $stmt->fetchColumn() + 1), 3, '0', STR_PAD_LEFT);
    }
}
