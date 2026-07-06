<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $users */
/** @var array<int,array<string,mixed>> $clients */
/** @var array<int,array<string,mixed>> $subcontractors */
/** @var string $search */
/** @var string $role */
/** @var string[] $roles */
/** @var array|null $user  current session user (from layout share) */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.users.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.users.subtitle')) ?></p>
    </div>
    <button type="button" class="btn btn-success js-crud-new" data-bs-toggle="modal" data-bs-target="#user-modal" data-target-modal="#user-modal">
        <?= $e($t('admin.users.new')) ?>
    </button>
</div>

<form method="get" class="row g-2 mb-3">
    <div class="col-12 col-sm-4">
        <input type="text" class="form-control" name="q" value="<?= $e($search) ?>" placeholder="<?= $e($t('common.search')) ?>">
    </div>
    <div class="col-6 col-sm-3">
        <select class="form-select" name="role">
            <option value=""><?= $e($t('common.all')) ?></option>
            <?php foreach ($roles as $r): ?>
                <option value="<?= $e($r) ?>" <?= $role === $r ? 'selected' : '' ?>><?= $e(Lang::label('roles', $r)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-auto">
        <button type="submit" class="btn btn-outline-secondary"><?= $e($t('common.search')) ?></button>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= $e($t('admin.users.name')) ?></th>
                    <th><?= $e($t('admin.users.email')) ?></th>
                    <th><?= $e($t('admin.users.role')) ?></th>
                    <th><?= $e($t('admin.users.client')) ?></th>
                    <th><?= $e($t('admin.users.active')) ?></th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($users === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= $e($t('admin.users.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($users as $u): ?>
                <tr class="<?= ((int) $u['is_active']) === 1 ? '' : 'table-secondary text-muted' ?>">
                    <td><?= $e($u['name']) ?></td>
                    <td><?= $e($u['email']) ?></td>
                    <td><span class="badge text-bg-light border"><?= $e(Lang::label('roles', $u['role'])) ?></span></td>
                    <td><?= $e($u['client_name'] ?? $u['subcontractor_name'] ?? '—') ?></td>
                    <td><?= ((int) $u['is_active']) === 1 ? $e($t('common.yes')) : $e($t('common.no')) ?></td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-secondary js-crud-edit"
                                data-bs-toggle="modal" data-bs-target="#user-modal" data-target-modal="#user-modal"
                                data-record='<?= $e(json_encode($u, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS)) ?>'>
                            <?= $e($t('common.edit')) ?>
                        </button>
                        <?php if ((int) $u['id'] !== (int) ($user['id'] ?? 0)): ?>
                            <button type="button" class="btn btn-sm btn-outline-warning js-toggle-active"
                                    data-url="<?= $e(Url::to('/admin/users/' . $u['id'] . '/toggle')) ?>">
                                <?= ((int) $u['is_active']) === 1 ? $e($t('admin.users.deactivate')) : $e($t('admin.users.activate')) ?>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="user-modal" tabindex="-1" data-title-create="<?= $e($t('admin.users.new')) ?>" data-title-edit="<?= $e($t('admin.users.edit')) ?>">
    <div class="modal-dialog">
        <div class="modal-content">
            <form class="js-crud-form" data-base-url="<?= $e(Url::to('/admin/users')) ?>">
                <div class="modal-header">
                    <h2 class="modal-title h5"><?= $e($t('admin.users.new')) ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none js-crud-error" role="alert"></div>
                    <input type="hidden" name="id">
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('admin.users.name')) ?></label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= $e($t('admin.users.email')) ?></label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label"><?= $e($t('admin.users.role')) ?></label>
                            <select class="form-select js-user-role" name="role" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $e($r) ?>"><?= $e(Lang::label('roles', $r)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 mb-3 js-user-client-field">
                            <label class="form-label"><?= $e($t('admin.users.client')) ?></label>
                            <select class="form-select" name="client_id">
                                <option value=""><?= $e($t('admin.users.client_placeholder')) ?></option>
                                <?php foreach ($clients as $c): ?>
                                    <option value="<?= $e((string) $c['id']) ?>"><?= $e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 mb-3 js-user-subcontractor-field d-none">
                            <label class="form-label"><?= $e($t('admin.users.subcontractor')) ?></label>
                            <select class="form-select" name="subcontractor_id">
                                <option value=""><?= $e($t('admin.users.subcontractor_placeholder')) ?></option>
                                <?php foreach ($subcontractors as $s): ?>
                                    <option value="<?= $e((string) $s['id']) ?>"><?= $e($s['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label"><?= $e($t('admin.users.password')) ?></label>
                        <input type="password" class="form-control" name="password" minlength="8" autocomplete="new-password">
                        <div class="form-text"><?= $e($t('admin.users.password_help')) ?></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= $e($t('common.cancel')) ?></button>
                    <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
