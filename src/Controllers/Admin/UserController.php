<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AuthController;
use App\Http\Middleware\AuthGuard;
use App\Models\ClientModel;
use App\Models\SubcontractorModel;
use App\Models\UserModel;
use App\Support\Auth;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\View;

/**
 * Admin user management (gap F1): onboard workers and client logins, edit,
 * activate/deactivate, reset passwords. Self-lockout is prevented server-side
 * (an admin cannot deactivate or demote their own account).
 */
final class UserController
{
    private const ROLES = ['admin', 'worker', 'client', 'subcontractor'];

    public function index(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $search = trim((string) $request->input('q', ''));
        $role   = (string) $request->input('role', '');
        if (!in_array($role, self::ROLES, true)) {
            $role = '';
        }

        Response::html(View::render('admin/users/index', [
            'title'          => Lang::get('admin.users.title'),
            'users'          => (new UserModel())->all($search, $role),
            'clients'        => (new ClientModel())->all(),
            'subcontractors' => (new SubcontractorModel())->listActive(),
            'search'         => $search,
            'role'           => $role,
            'roles'          => self::ROLES,
        ], 'layout'));
    }

    /** GET /admin/users/create — blank user form page. */
    public function create(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        Response::html(View::render('admin/users/form', [
            'title'          => Lang::get('admin.users.new'),
            'record'         => null,
            'clients'        => (new ClientModel())->all(),
            'subcontractors' => (new SubcontractorModel())->listActive(),
            'roles'          => self::ROLES,
        ], 'layout'));
    }

    /** GET /admin/users/{id}/edit — populated user form page. */
    public function edit(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $record = (new UserModel())->findById((int) $id);
        if ($record === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        Response::html(View::render('admin/users/form', [
            'title'          => Lang::get('admin.users.edit'),
            'record'         => $record,
            'clients'        => (new ClientModel())->all(),
            'subcontractors' => (new SubcontractorModel())->listActive(),
            'roles'          => self::ROLES,
        ], 'layout'));
    }

    public function store(Request $request): void
    {
        AuthGuard::require($request, ['admin']);

        $data = $this->validated($request, null);
        if ($data === null) {
            return;
        }

        $password = (string) $request->input('password', '');
        if (strlen($password) < AuthController::MIN_PASSWORD_LENGTH) {
            Response::fail(sprintf(Lang::get('auth.password_too_short'), AuthController::MIN_PASSWORD_LENGTH), 422);
            return;
        }
        $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);

        $id = (new UserModel())->create($data);
        Response::ok(['id' => $id]);
    }

    public function update(Request $request, string $id): void
    {
        $me = AuthGuard::require($request, ['admin']);

        $model = new UserModel();
        $user  = $model->findById((int) $id);
        if ($user === null) {
            Response::fail(Lang::get('admin.users.not_found'), 404);
            return;
        }

        $data = $this->validated($request, (int) $id);
        if ($data === null) {
            return;
        }

        // Never let an admin demote their own account (self-lockout).
        if ((int) $id === (int) $me['id'] && $data['role'] !== 'admin') {
            Response::fail(Lang::get('admin.users.cannot_edit_self'), 422);
            return;
        }

        $model->update((int) $id, $data);

        // Optional password reset piggybacked on the edit form.
        $password = (string) $request->input('password', '');
        if ($password !== '') {
            if (strlen($password) < AuthController::MIN_PASSWORD_LENGTH) {
                Response::fail(sprintf(Lang::get('auth.password_too_short'), AuthController::MIN_PASSWORD_LENGTH), 422);
                return;
            }
            $model->updatePassword((int) $id, password_hash($password, PASSWORD_DEFAULT));
        }

        Response::ok();
    }

    public function toggleActive(Request $request, string $id): void
    {
        $me = AuthGuard::require($request, ['admin']);

        if ((int) $id === (int) $me['id']) {
            Response::fail(Lang::get('admin.users.cannot_deactivate_self'), 422);
            return;
        }

        $model = new UserModel();
        $user  = $model->findById((int) $id);
        if ($user === null) {
            Response::fail(Lang::get('admin.users.not_found'), 404);
            return;
        }

        $model->setActive((int) $id, ((int) $user['is_active']) !== 1);
        Response::ok();
    }

    /** @return array<string,mixed>|null Validated fields, or null if a fail response was already sent. */
    private function validated(Request $request, ?int $excludeId): ?array
    {
        $name = trim((string) $request->input('name', ''));
        if ($name === '') {
            Response::fail(Lang::get('admin.users.name_required'), 422);
            return null;
        }

        $email = trim((string) $request->input('email', ''));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            Response::fail(Lang::get('admin.users.email_invalid'), 422);
            return null;
        }
        if ((new UserModel())->emailExists($email, $excludeId)) {
            Response::fail(Lang::get('admin.users.email_taken'), 422);
            return null;
        }

        $role = (string) $request->input('role', '');
        if (!in_array($role, self::ROLES, true)) {
            Response::fail(Lang::get('admin.users.role_invalid'), 422);
            return null;
        }

        // client_id is required for client logins, forbidden otherwise.
        $clientId = (int) $request->input('client_id', 0);
        if ($role === 'client') {
            if ($clientId <= 0 || (new ClientModel())->find($clientId) === null) {
                Response::fail(Lang::get('admin.users.client_required'), 422);
                return null;
            }
        } else {
            $clientId = 0;
        }

        // subcontractor_id is required for subcontractor logins, forbidden otherwise.
        $subcontractorId = (int) $request->input('subcontractor_id', 0);
        if ($role === 'subcontractor') {
            if ($subcontractorId <= 0 || (new SubcontractorModel())->find($subcontractorId) === null) {
                Response::fail(Lang::get('admin.users.subcontractor_required'), 422);
                return null;
            }
        } else {
            $subcontractorId = 0;
        }

        return [
            'name'             => $name,
            'email'            => $email,
            'role'             => $role,
            'client_id'        => $clientId > 0 ? $clientId : null,
            'subcontractor_id' => $subcontractorId > 0 ? $subcontractorId : null,
        ];
    }
}
