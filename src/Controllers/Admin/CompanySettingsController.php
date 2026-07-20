<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\CompanySettingsModel;
use App\Support\AuditLog;
use App\Support\Fiscal;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/**
 * "Dati Azienda" — the seller (CedentePrestatore) fiscal profile that feeds
 * document headers and the FatturaPA export. A single editable record.
 */
final class CompanySettingsController
{
    public function edit(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new CompanySettingsModel();
        Response::html(View::render('admin/company/form', [
            'title'      => Lang::get('admin.company.title'),
            'company'    => $model->get(),
            'isComplete' => $model->isComplete(),
            'regimi'     => Fiscal::REGIMI,
        ], 'layout'));
    }

    public function save(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $data = $this->validated($request);
        if ($data === null) {
            return;
        }

        (new CompanySettingsModel())->save($data);
        AuditLog::record('updated', 'company_settings', 1, (string) $data['denominazione']);
        Response::ok();
    }

    /** @return array<string,mixed>|null */
    private function validated(Request $request): ?array
    {
        $name = trim((string) $request->input('denominazione', ''));
        if ($name === '') {
            Response::fail(Lang::get('admin.company.name_required'), 422);
            return null;
        }

        $piva = strtoupper(str_replace(' ', '', (string) $request->input('partita_iva', '')));
        if ($piva !== '' && !Fiscal::isPartitaIva($piva)) {
            Response::fail(Lang::get('admin.clients.piva_invalid'), 422);
            return null;
        }
        $cf = strtoupper(str_replace(' ', '', (string) $request->input('codice_fiscale', '')));
        if ($cf !== '' && !Fiscal::isCodiceFiscale($cf)) {
            Response::fail(Lang::get('admin.clients.cf_invalid'), 422);
            return null;
        }
        $regime = (string) $request->input('regime_fiscale', 'RF01');
        if (!in_array($regime, Fiscal::REGIMI, true)) {
            $regime = 'RF01';
        }
        $prov = strtoupper(trim((string) $request->input('provincia', '')));
        if ($prov !== '' && !Fiscal::isProvincia($prov)) {
            Response::fail(Lang::get('admin.clients.provincia_invalid'), 422);
            return null;
        }
        $rea = strtoupper(trim((string) $request->input('rea_ufficio', '')));
        if ($rea !== '' && !Fiscal::isProvincia($rea)) {
            Response::fail(Lang::get('admin.clients.provincia_invalid'), 422);
            return null;
        }
        $capitale = trim((string) $request->input('capitale_sociale', ''));
        $socio    = (string) $request->input('socio_unico', '');
        $liq      = (string) $request->input('stato_liquidazione', 'LN');
        $naz      = strtoupper(trim((string) $request->input('nazione', 'IT')));
        if (!preg_match('/^[A-Z]{2}$/', $naz)) {
            $naz = 'IT';
        }

        return [
            'denominazione'      => $name,
            'partita_iva'        => $piva !== '' ? $piva : null,
            'codice_fiscale'     => $cf !== '' ? $cf : null,
            'regime_fiscale'     => $regime,
            'indirizzo'          => $this->nullable($request, 'indirizzo'),
            'numero_civico'      => $this->nullable($request, 'numero_civico'),
            'cap'                => $this->nullable($request, 'cap'),
            'comune'             => $this->nullable($request, 'comune'),
            'provincia'          => $prov !== '' ? $prov : null,
            'nazione'            => $naz,
            'telefono'           => $this->nullable($request, 'telefono'),
            'email'              => $this->nullable($request, 'email'),
            'pec'                => $this->nullable($request, 'pec'),
            'rea_ufficio'        => $rea !== '' ? $rea : null,
            'rea_numero'         => $this->nullable($request, 'rea_numero'),
            'capitale_sociale'   => is_numeric($capitale) ? $capitale : null,
            'socio_unico'        => in_array($socio, ['SU', 'SM'], true) ? $socio : null,
            'stato_liquidazione' => in_array($liq, ['LS', 'LN'], true) ? $liq : 'LN',
            'iban'               => $this->nullable($request, 'iban'),
        ];
    }

    private function nullable(Request $request, string $key): ?string
    {
        $value = trim((string) $request->input($key, ''));
        return $value !== '' ? $value : null;
    }
}
