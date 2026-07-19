<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Http\Middleware\AuthGuard;
use App\Models\ClientModel;
use App\Models\LeadModel;
use App\Support\AuditLog;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\Url;
use App\Support\View;

/**
 * Admin lead inbox: work public "request a job" submissions through a small status
 * workflow and convert a promising lead into a client with one click.
 */
final class LeadController
{
    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $status = (string) $request->input('status', '');
        if (!in_array($status, LeadModel::statuses(), true)) {
            $status = '';
        }

        $model = new LeadModel();
        Response::html(View::render('admin/leads/index', [
            'title'  => Lang::get('admin.leads.title'),
            'leads'  => $model->all($status),
            'counts' => $model->countByStatus(),
            'status' => $status,
        ], 'layout'));
    }

    public function show(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $lead = (new LeadModel())->find((int) $id);
        if ($lead === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }
        $client = $lead['client_id'] !== null ? (new ClientModel())->find((int) $lead['client_id']) : null;

        Response::html(View::render('admin/leads/show', [
            'title'    => (string) $lead['name'],
            'lead'     => $lead,
            'client'   => $client,
            'statuses' => LeadModel::statuses(),
        ], 'layout'));
    }

    public function setStatus(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new LeadModel();
        if ($model->find((int) $id) === null) {
            Response::fail(Lang::get('admin.leads.not_found'), 404);
            return;
        }

        $status = (string) $request->input('status', '');
        if (!in_array($status, LeadModel::statuses(), true)) {
            Response::fail(Lang::get('admin.leads.status_invalid'), 422);
            return;
        }
        $model->setStatus((int) $id, $status);
        Response::ok();
    }

    /** POST /admin/leads/{id}/convert — create a client from the lead and link it. */
    public function convert(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new LeadModel();
        $lead  = $model->find((int) $id);
        if ($lead === null) {
            Response::fail(Lang::get('admin.leads.not_found'), 404);
            return;
        }
        if ($lead['client_id'] !== null) {
            Response::fail(Lang::get('admin.leads.already_converted'), 422);
            return;
        }

        $clientId = (new ClientModel())->create([
            'name'          => (string) $lead['name'],
            'vat_or_tax_id' => null,
            'email'         => $lead['email'],
            'phone'         => $lead['phone'],
            'address'       => null,
            'notes'         => $lead['message'],
        ]);
        $model->markConverted((int) $id, $clientId);
        AuditLog::record('created', 'client', $clientId, (string) $lead['name']);

        Response::ok(['redirect' => Url::to('/admin/clients/' . $clientId)]);
    }

    public function delete(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model = new LeadModel();
        if ($model->find((int) $id) === null) {
            Response::fail(Lang::get('admin.leads.not_found'), 404);
            return;
        }
        $model->delete((int) $id);
        Response::ok();
    }
}
