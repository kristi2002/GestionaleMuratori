<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * The e-invoice lifecycle record for a fiscal invoice (migration 036): which XML /
 * signed file was produced, the SdI transmission status, and any receipt identifier.
 * One row per invoice, upserted each time it is (re)prepared.
 */
final class EInvoiceModel
{
    public function forInvoice(int $invoiceId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM einvoice_documents WHERE invoice_id = ? LIMIT 1');
        $stmt->execute([$invoiceId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @param array<int> $invoiceIds
     * @return array<int,array<string,mixed>> invoice_id => record (for list badges).
     */
    public function forInvoices(array $invoiceIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $invoiceIds)));
        if ($ids === []) {
            return [];
        }
        $in   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare("SELECT * FROM einvoice_documents WHERE invoice_id IN ($in)");
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int) $row['invoice_id']] = $row;
        }
        return $out;
    }

    /** Insert or update the single record for an invoice. */
    public function upsert(int $invoiceId, array $data): void
    {
        $fields = ['format', 'progressivo', 'status', 'xml_path', 'signed_path', 'sdi_identifier', 'message'];
        $set    = [];
        $params = [':invoice_id' => $invoiceId];
        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $params[":$f"] = $data[$f];
            }
        }

        $existing = $this->forInvoice($invoiceId);
        if ($existing === null) {
            $cols = array_keys($params);
            $names = implode(', ', array_map(static fn (string $c): string => ltrim($c, ':'), $cols));
            $vals  = implode(', ', $cols);
            Database::pdo()->prepare("INSERT INTO einvoice_documents ($names) VALUES ($vals)")->execute($params);
            return;
        }
        foreach ($params as $k => $v) {
            if ($k !== ':invoice_id') {
                $set[] = ltrim($k, ':') . ' = ' . $k;
            }
        }
        if ($set === []) {
            return;
        }
        Database::pdo()->prepare(
            'UPDATE einvoice_documents SET ' . implode(', ', $set) . ' WHERE invoice_id = :invoice_id'
        )->execute($params);
    }
}
