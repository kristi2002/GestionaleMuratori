<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\ClientModel;
use App\Support\AuditLog;
use App\Support\Csv;
use App\Support\Fiscal;
use App\Support\Lang;
use App\Support\Paginator;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

final class ClientController
{
    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $search    = trim((string) $request->input('q', ''));
        $model     = new ClientModel();
        $paginator = Paginator::fromRequest($request, $model->count($search), 24);

        Response::html(View::render('admin/clients/index', [
            'title'          => Lang::get('admin.clients.title'),
            'clients'        => $model->all($search, $paginator->perPage, $paginator->offset),
            'search'         => $search,
            'paginator'      => $paginator,
            'projectsTotal'  => $model->totalProjects(),
            'invoicedTotal'  => $model->totalInvoiced(),
        ], 'layout'));
    }

    /** GET /admin/clients/export — CSV of the (optionally searched) clients. */
    public function exportCsv(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $rows = (new ClientModel())->all(trim((string) $request->input('q', '')));
        $data = array_map(static fn (array $c): array => [
            $c['name'], $c['vat_or_tax_id'], $c['email'], $c['phone'], $c['address'],
        ], $rows);

        Csv::send('clienti.csv', [
            Lang::get('admin.clients.name'),
            Lang::get('admin.clients.vat'),
            Lang::get('admin.clients.email'),
            Lang::get('admin.clients.phone'),
            Lang::get('admin.clients.address'),
        ], $data);
    }

    /** GET /admin/clients/create — blank client form page. */
    public function create(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        Response::html(View::render('admin/clients/form', [
            'title'  => Lang::get('admin.clients.new'),
            'client' => null,
        ], 'layout'));
    }

    /** GET /admin/clients/{id} — client profile: contacts, financials, projects, activity. */
    public function show(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model  = new ClientModel();
        $client = $model->find((int) $id);
        if ($client === null) {
            Response::html(View::render('errors/404', ['title' => Lang::get('admin.clients.not_found')], 'layout'), 404);
            return;
        }

        Response::html(View::render('admin/clients/show', [
            'title'     => (string) $client['name'],
            'client'    => $client,
            'stats'     => $model->profileStats((int) $id),
            'projects'  => $model->projectsForProfile((int) $id),
            'interventions' => $model->interventionsForProfile((int) $id),
            'quotes'    => $model->quotesForProfile((int) $id),
            'invoices'  => $model->invoicesForProfile((int) $id),
            'lead'      => $model->leadForClient((int) $id),
            'monthly'   => $model->monthlyInvoiced((int) $id),
            'timeline'  => $model->activityTimeline((int) $id),
        ], 'layout'));
    }

    /** GET /admin/clients/{id}/edit — populated client form page. */
    public function edit(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $client = (new ClientModel())->find((int) $id);
        if ($client === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        Response::html(View::render('admin/clients/form', [
            'title'  => Lang::get('admin.clients.edit'),
            'client' => $client,
        ], 'layout'));
    }

    public function store(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $data = $this->validated($request);
        if ($data === null) {
            return;
        }

        $id = (new ClientModel())->create($data);
        Response::ok(['id' => $id]);
    }

    public function update(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model  = new ClientModel();
        $client = $model->find((int) $id);
        if ($client === null) {
            Response::fail(Lang::get('admin.clients.not_found'), 404);
            return;
        }

        $data = $this->validated($request);
        if ($data === null) {
            return;
        }

        $model->update((int) $id, $data);
        Response::ok();
    }

    public function destroy(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model  = new ClientModel();
        $client = $model->find((int) $id);
        if ($client === null) {
            Response::fail(Lang::get('admin.clients.not_found'), 404);
            return;
        }

        $model->delete((int) $id);
        AuditLog::record('deleted', 'client', (int) $id, (string) $client['name']);
        Response::ok();
    }

    /** @return array<string,mixed>|null Validated fields, or null if a fail response was already sent. */
    private function validated(Request $request): ?array
    {
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Response::fail(Lang::get('admin.clients.name_required'), 422);
            return null;
        }

        $email = trim((string) $request->input('email', ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Response::fail(Lang::get('admin.clients.email_invalid'), 422);
            return null;
        }

        $pec = trim((string) $request->input('pec', ''));
        if ($pec !== '' && filter_var($pec, FILTER_VALIDATE_EMAIL) === false) {
            Response::fail(Lang::get('admin.clients.pec_invalid'), 422);
            return null;
        }

        // Fiscal identifiers are optional, but if given must be well-formed —
        // the FatturaPA builder relies on these being clean.
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
        $sdi = strtoupper(trim((string) $request->input('codice_destinatario', '')));
        if ($sdi !== '' && !Fiscal::isCodiceDestinatario($sdi)) {
            Response::fail(Lang::get('admin.clients.sdi_invalid'), 422);
            return null;
        }
        $prov = strtoupper(trim((string) $request->input('provincia', '')));
        if ($prov !== '' && !Fiscal::isProvincia($prov)) {
            Response::fail(Lang::get('admin.clients.provincia_invalid'), 422);
            return null;
        }
        $kind = (string) $request->input('client_kind', 'business');
        if (!in_array($kind, ['business', 'private', 'pa'], true)) {
            $kind = 'business';
        }
        $naz = strtoupper(trim((string) $request->input('nazione', 'IT')));
        if (!preg_match('/^[A-Z]{2}$/', $naz)) {
            $naz = 'IT';
        }

        return [
            'name'                => $name,
            'client_kind'         => $kind,
            'vat_or_tax_id'       => $this->nullableInput($request, 'vat_or_tax_id'),
            'partita_iva'         => $piva !== '' ? $piva : null,
            'codice_fiscale'      => $cf !== '' ? $cf : null,
            'codice_destinatario' => $sdi !== '' ? $sdi : null,
            'pec'                 => $pec !== '' ? $pec : null,
            'email'               => $email !== '' ? $email : null,
            'phone'               => $this->nullableInput($request, 'phone'),
            'address'             => $this->nullableInput($request, 'address'),
            'cap'                 => $this->nullableInput($request, 'cap'),
            'comune'              => $this->nullableInput($request, 'comune'),
            'provincia'           => $prov !== '' ? $prov : null,
            'nazione'             => $naz,
            'notes'               => $this->nullableInput($request, 'notes'),
        ];
    }

    private function nullableInput(Request $request, string $key): ?string
    {
        $value = trim((string) $request->input($key, ''));
        return $value !== '' ? $value : null;
    }
}
