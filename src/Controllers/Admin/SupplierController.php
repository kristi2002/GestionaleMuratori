<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\SupplierModel;
use App\Support\Lang;
use App\Support\Paginator;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/**
 * Admin management of suppliers (fornitori): CRUD plus activate/deactivate. A supplier
 * is the counterparty of a purchase order (buono d'ordine) — a materials vendor, kept
 * distinct from subcontractors (who do work on site and have a portal login).
 */
final class SupplierController
{
    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $search    = trim((string) $request->input('q', ''));
        $model     = new SupplierModel();
        $paginator = Paginator::fromRequest($request, $model->count($search), 24);

        Response::html(View::render('admin/suppliers/index', [
            'title'     => Lang::get('admin.suppliers.title'),
            'suppliers' => $model->all($search, $paginator->perPage, $paginator->offset),
            'stats'     => $model->stats(),
            'search'    => $search,
            'paginator' => $paginator,
        ], 'layout'));
    }

    /** GET /admin/suppliers/create — blank supplier form page. */
    public function create(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        Response::html(View::render('admin/suppliers/form', [
            'title'    => Lang::get('admin.suppliers.new'),
            'supplier' => null,
        ], 'layout'));
    }

    /** GET /admin/suppliers/{id}/edit — populated supplier form page. */
    public function edit(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $supplier = (new SupplierModel())->find((int) $id);
        if ($supplier === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        Response::html(View::render('admin/suppliers/form', [
            'title'    => Lang::get('admin.suppliers.edit'),
            'supplier' => $supplier,
        ], 'layout'));
    }

    public function store(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $data = $this->validated($request);
        if ($data === null) {
            return;
        }

        $id = (new SupplierModel())->create($data);
        Response::ok(['id' => $id]);
    }

    public function update(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new SupplierModel();
        if ($model->find((int) $id) === null) {
            Response::fail(Lang::get('admin.suppliers.not_found'), 404);
            return;
        }

        $data = $this->validated($request);
        if ($data === null) {
            return;
        }

        $model->update((int) $id, $data);
        Response::ok();
    }

    public function toggleActive(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model    = new SupplierModel();
        $supplier = $model->find((int) $id);
        if ($supplier === null) {
            Response::fail(Lang::get('admin.suppliers.not_found'), 404);
            return;
        }

        $model->setActive((int) $id, ((int) $supplier['is_active']) !== 1);
        Response::ok();
    }

    /** @return array<string,mixed>|null Validated fields, or null if a fail response was already sent. */
    private function validated(Request $request): ?array
    {
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Response::fail(Lang::get('admin.suppliers.name_required'), 422);
            return null;
        }

        $email = trim((string) $request->input('email', ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Response::fail(Lang::get('admin.suppliers.email_invalid'), 422);
            return null;
        }

        $vat     = trim((string) $request->input('vat_or_tax_id', ''));
        $phone   = trim((string) $request->input('phone', ''));
        $address = trim((string) $request->input('address', ''));
        $notes   = trim((string) $request->input('notes', ''));

        return [
            'name'          => mb_substr($name, 0, 190),
            'vat_or_tax_id' => $vat !== '' ? mb_substr($vat, 0, 50) : null,
            'email'         => $email !== '' ? mb_substr($email, 0, 190) : null,
            'phone'         => $phone !== '' ? mb_substr($phone, 0, 50) : null,
            'address'       => $address !== '' ? mb_substr($address, 0, 255) : null,
            'notes'         => $notes !== '' ? $notes : null,
        ];
    }
}
