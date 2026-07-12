<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\ClientModel;
use App\Support\AuditLog;
use App\Support\Csv;
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
            'title'     => Lang::get('admin.clients.title'),
            'clients'   => $model->all($search, $paginator->perPage, $paginator->offset),
            'search'    => $search,
            'paginator' => $paginator,
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

        return [
            'name'          => $name,
            'vat_or_tax_id' => $this->nullableInput($request, 'vat_or_tax_id'),
            'email'         => $email !== '' ? $email : null,
            'phone'         => $this->nullableInput($request, 'phone'),
            'address'       => $this->nullableInput($request, 'address'),
            'notes'         => $this->nullableInput($request, 'notes'),
        ];
    }

    private function nullableInput(Request $request, string $key): ?string
    {
        $value = trim((string) $request->input($key, ''));
        return $value !== '' ? $value : null;
    }
}
