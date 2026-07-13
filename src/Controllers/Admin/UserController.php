<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AuthController;
use App\Http\Middleware\AuthGuard;
use App\Models\ClientModel;
use App\Models\SubcontractorModel;
use App\Models\UserModel;
use App\Services\PhotoStreamService;
use App\Services\UserProfileService;
use App\Support\AuditLog;
use App\Support\Auth;
use App\Support\Lang;
use App\Support\Request;
use App\Support\Response;
use App\Support\Storage\Storage;
use App\Support\Url;
use App\Support\View;

/**
 * Admin user management (gap F1): onboard workers and client logins, edit,
 * activate/deactivate, reset passwords. Self-lockout is prevented server-side
 * (an admin cannot deactivate or demote their own account).
 */
final class UserController
{
    private const ROLES = ['admin', 'worker', 'client', 'subcontractor'];
    private const AVATAR_MAX_BYTES = 4 * 1024 * 1024;

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

    /** GET /admin/users/{id} — read-only profile page (identity, presence, tasks, docs). */
    public function show(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $record = (new UserModel())->findById((int) $id);
        if ($record === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        Response::html(View::render('admin/users/show', [
            'title'      => (string) $record['name'],
            'record'     => $record,
            'profile'    => (new UserProfileService())->forUser((int) $id),
            'hasAvatar'  => ($record['avatar_path'] ?? null) !== null,
            'avatarErr'  => ($request->input('err', '') === 'avatar'),
        ], 'layout'));
    }

    /** GET /admin/users/{id}/avatar — stream the stored avatar (permission-checked). */
    public function avatar(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $record = (new UserModel())->findById((int) $id);
        if ($record === null || ($record['avatar_path'] ?? null) === null
            || !(new PhotoStreamService())->streamFile((string) $record['avatar_path'])) {
            http_response_code(404);
        }
    }

    /** POST /admin/users/{id}/avatar — replace the user's avatar image. */
    public function uploadAvatar(Request $request, string $id): void
    {
        AuthGuard::require($request, ['admin']);

        $model  = new UserModel();
        $record = $model->findById((int) $id);
        if ($record === null) {
            Response::html(View::render('errors/404', ['title' => 'Pagina non trovata'], 'layout'), 404);
            return;
        }

        $back = Url::to('/admin/users/' . (int) $id);
        $file = $_FILES['avatar'] ?? null;
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || (int) $file['size'] > self::AVATAR_MAX_BYTES) {
            Response::redirect($back . '?err=avatar');
            return;
        }

        $info = @getimagesize($file['tmp_name']);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            Response::redirect($back . '?err=avatar');
            return;
        }

        $ext     = $info[2] === IMAGETYPE_PNG ? 'png' : 'jpg';
        $relPath = 'avatars/' . (int) $id . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

        $storage = Storage::disk();
        $storage->putUploadedFile($relPath, $file['tmp_name']);

        // Drop the previous avatar file once the new one is safely stored.
        $old = $record['avatar_path'] ?? null;
        $model->setAvatarPath((int) $id, $relPath);
        if ($old !== null && $old !== $relPath) {
            $storage->delete((string) $old);
        }
        AuditLog::record('updated', 'user', (int) $id, (string) $record['name']);

        Response::redirect($back);
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
        AuditLog::record('created', 'user', $id, (string) ($data['name'] ?? ''));
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
        AuditLog::record('updated', 'user', (int) $id, (string) ($data['name'] ?? $user['name']));

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

        $newActive = ((int) $user['is_active']) !== 1;
        $model->setActive((int) $id, $newActive);
        AuditLog::record($newActive ? 'activated' : 'deactivated', 'user', (int) $id, (string) $user['name']);
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

        // Optional profile fields (operaio detail page). hire_date must be a
        // plain calendar date when present; blank clears it.
        $jobTitle = trim((string) $request->input('job_title', ''));
        $phone    = trim((string) $request->input('phone', ''));
        $hireDate = trim((string) $request->input('hire_date', ''));
        if ($hireDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hireDate)) {
            Response::fail(Lang::get('admin.users.hire_date_invalid'), 422);
            return null;
        }

        return [
            'name'             => $name,
            'job_title'        => $jobTitle !== '' ? $jobTitle : null,
            'email'            => $email,
            'phone'            => $phone !== '' ? $phone : null,
            'hire_date'        => $hireDate !== '' ? $hireDate : null,
            'role'             => $role,
            'client_id'        => $clientId > 0 ? $clientId : null,
            'subcontractor_id' => $subcontractorId > 0 ? $subcontractorId : null,
        ];
    }
}
