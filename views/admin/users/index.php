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
    <a class="btn btn-success" href="<?= $e(Url::to('/admin/users/create')) ?>">
        <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= $e($t('admin.users.new')) ?>
    </a>
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
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/users/' . $u['id'] . '/edit')) ?>">
                            <?= $e($t('common.edit')) ?>
                        </a>
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
