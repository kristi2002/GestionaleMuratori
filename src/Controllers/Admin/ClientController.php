<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\ClientModel;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

final class ClientController
{
    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $search  = trim((string) $request->input('q', ''));
        $clients = (new ClientModel())->all($search);

        Response::html(View::render('admin/clients/index', [
            'title'   => Lang::get('admin.clients.title'),
            'clients' => $clients,
            'search'  => $search,
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
