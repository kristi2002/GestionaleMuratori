<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Config;
use App\Support\Database;

/**
 * The CedentePrestatore (seller) fiscal profile — a single row (id = 1) seeded by
 * migration 032. Feeds the invoice/quote/S.A.L. PDF headers and, later, the
 * FatturaPA CedentePrestatore block. Legacy COMPANY_* env values fill the basic
 * fields until an admin saves the richer profile under "Dati Azienda".
 */
final class CompanySettingsModel
{
    private const ID = 1;

    /** Columns the admin form may write (id/updated_at are managed here). */
    private const FIELDS = [
        'denominazione', 'partita_iva', 'codice_fiscale', 'regime_fiscale',
        'indirizzo', 'numero_civico', 'cap', 'comune', 'provincia', 'nazione',
        'telefono', 'email', 'pec', 'rea_ufficio', 'rea_numero',
        'capitale_sociale', 'socio_unico', 'stato_liquidazione', 'iban',
    ];

    /** The stored profile, with legacy COMPANY_* env values as fallback for empty basics. */
    public function get(): array
    {
        $row = Database::pdo()->query('SELECT * FROM company_settings WHERE id = ' . self::ID)->fetch()
            ?: ['id' => self::ID];

        // Fallbacks so a fresh install still prints a usable PDF header.
        if (trim((string) ($row['denominazione'] ?? '')) === '') {
            $row['denominazione'] = (string) Config::get('company.name', '');
        }
        if (trim((string) ($row['email'] ?? '')) === '') {
            $row['email'] = (string) Config::get('company.email', '');
        }
        if (trim((string) ($row['telefono'] ?? '')) === '') {
            $row['telefono'] = (string) Config::get('company.phone', '');
        }
        return $row;
    }

    /**
     * True once the seller profile carries the minimum a FatturaPA needs:
     * a name, a P.IVA (or C.F.), a regime, and a complete address.
     */
    public function isComplete(): bool
    {
        $r = $this->get();
        return trim((string) $r['denominazione']) !== ''
            && (trim((string) ($r['partita_iva'] ?? '')) !== '' || trim((string) ($r['codice_fiscale'] ?? '')) !== '')
            && trim((string) ($r['regime_fiscale'] ?? '')) !== ''
            && trim((string) ($r['indirizzo'] ?? '')) !== ''
            && trim((string) ($r['cap'] ?? '')) !== ''
            && trim((string) ($r['comune'] ?? '')) !== ''
            && trim((string) ($r['provincia'] ?? '')) !== '';
    }

    /** Persist the editable fields onto the single settings row. */
    public function save(array $data): void
    {
        $sets   = [];
        $params = [':id' => self::ID];
        foreach (self::FIELDS as $f) {
            if (array_key_exists($f, $data)) {
                $sets[]        = "$f = :$f";
                $params[":$f"] = $data[$f];
            }
        }
        if ($sets === []) {
            return;
        }
        $stmt = Database::pdo()->prepare(
            'UPDATE company_settings SET ' . implode(', ', $sets) . ' WHERE id = :id'
        );
        $stmt->execute($params);
    }
}
