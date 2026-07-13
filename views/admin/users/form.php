<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed>|null $record null = create, row = edit */
/** @var array<int,array<string,mixed>> $clients */
/** @var array<int,array<string,mixed>> $subcontractors */
/** @var string[] $roles */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$isEdit       = $record !== null;
$pageTitle    = $isEdit ? $t('admin.users.edit') : $t('admin.users.new');
$value        = static fn (string $key): string => (string) ($record[$key] ?? '');
$selectedRole = $isEdit ? (string) ($record['role'] ?? '') : (string) ($roles[0] ?? '');
?>
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($pageTitle) ?></h1>
        <p class="text-muted mb-0"><?= $e($isEdit ? (string) $record['name'] : $t('admin.users.subtitle')) ?></p>
    </div>
    <?= View::render('partials/back_button', ['href' => '/admin/users'], null) ?>
</div>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.users.title'), '/admin/users'],
    [$isEdit ? (string) $record['name'] : $t('admin.users.new'), null],
]], null) ?>

<div class="card">
    <div class="card-body">
        <form class="js-crud-form"
              data-base-url="<?= $e(Url::to('/admin/users')) ?>"
              data-redirect="<?= $e(Url::to('/admin/users')) ?>">
            <input type="hidden" name="id" value="<?= $e($isEdit ? (string) $record['id'] : '') ?>">

            <div class="row">
                <div class="col-12 col-md-7 mb-3">
                    <label class="form-label"><?= $e($t('admin.users.name')) ?></label>
                    <input type="text" class="form-control" name="name" value="<?= $e($value('name')) ?>" required>
                </div>
                <div class="col-12 col-md-5 mb-3">
                    <label class="form-label"><?= $e($t('admin.users.job_title')) ?></label>
                    <input type="text" class="form-control" name="job_title" value="<?= $e($value('job_title')) ?>"
                           placeholder="<?= $e($t('admin.users.job_title_placeholder')) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.users.email')) ?></label>
                    <input type="email" class="form-control" name="email" value="<?= $e($value('email')) ?>" required>
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.users.phone')) ?></label>
                    <input type="text" class="form-control" name="phone" value="<?= $e($value('phone')) ?>">
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.users.hire_date')) ?></label>
                    <input type="date" class="form-control" name="hire_date" value="<?= $e($value('hire_date')) ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.users.role')) ?></label>
                    <select class="form-select js-user-role" name="role" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= $e($r) ?>" <?= $selectedRole === $r ? 'selected' : '' ?>><?= $e(Lang::label('roles', $r)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6 mb-3 js-user-client-field <?= $selectedRole === 'client' ? '' : 'd-none' ?>">
                    <label class="form-label"><?= $e($t('admin.users.client')) ?></label>
                    <select class="form-select" name="client_id">
                        <option value=""><?= $e($t('admin.users.client_placeholder')) ?></option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $e((string) $c['id']) ?>" <?= (int) $value('client_id') === (int) $c['id'] ? 'selected' : '' ?>><?= $e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6 mb-3 js-user-subcontractor-field <?= $selectedRole === 'subcontractor' ? '' : 'd-none' ?>">
                    <label class="form-label"><?= $e($t('admin.users.subcontractor')) ?></label>
                    <select class="form-select" name="subcontractor_id">
                        <option value=""><?= $e($t('admin.users.subcontractor_placeholder')) ?></option>
                        <?php foreach ($subcontractors as $s): ?>
                            <option value="<?= $e((string) $s['id']) ?>" <?= (int) $value('subcontractor_id') === (int) $s['id'] ? 'selected' : '' ?>><?= $e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.users.password')) ?></label>
                <input type="password" class="form-control" name="password" minlength="8" autocomplete="new-password" value="">
                <div class="form-text"><?= $e($t('admin.users.password_help')) ?></div>
            </div>

            <div class="alert alert-danger d-none js-crud-error" role="alert"></div>

            <div class="d-flex gap-2 pt-3 border-top">
                <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/users')) ?>"><?= $e($t('common.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
