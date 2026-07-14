<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $users */
/** @var array<string,int> $roleCounts  role => count (+ '_total'), from UserModel::countsByRole() */
/** @var array<int,array<string,mixed>> $clients */
/** @var array<int,array<string,mixed>> $subcontractors */
/** @var string $search */
/** @var string $role */
/** @var string[] $roles */
/** @var array|null $user  current session user (from layout share) */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

// Compact initials avatar from a display name (same pattern as projects/index.php).
$initials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $ini = '';
    foreach ($parts as $p) {
        if ($p !== '') { $ini .= mb_strtoupper(mb_substr($p, 0, 1)); }
        if (mb_strlen($ini) >= 2) { break; }
    }
    return $ini !== '' ? $ini : '—';
};

// Users-list URL that keeps the active search while switching the role pill.
$pillHref = static function (string $roleValue) use ($search): string {
    $q = array_filter([
        'q'    => $search,
        'role' => $roleValue,
    ], static fn ($v): bool => $v !== '' && $v !== null);
    return '/admin/users' . ($q !== [] ? '?' . http_build_query($q) : '');
};

$actions = '<a class="btn btn-success" href="' . $e(Url::to('/admin/users/create')) . '">'
    . '<i class="bi bi-plus-lg" aria-hidden="true"></i> ' . $e($t('admin.users.new')) . '</a>'
    . View::render('partials/back_button', ['href' => '/admin'], null);

echo View::render('partials/page_head', [
    'title'    => $t('admin.users.title'),
    'subtitle' => $t('admin.users.subtitle'),
    'actions'  => $actions,
], null);
?>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card gm-kpi is-primary h-100">
            <i class="bi bi-people gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e((string) ($roleCounts['_total'] ?? 0)) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('admin.users.kpi_total')) ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card gm-kpi is-purple h-100">
            <i class="bi bi-shield-lock gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e((string) ($roleCounts['admin'] ?? 0)) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('admin.users.kpi_admins')) ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card gm-kpi is-info h-100">
            <i class="bi bi-person-badge gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e((string) ($roleCounts['worker'] ?? 0)) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('admin.users.kpi_workers')) ?></div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card gm-kpi ok h-100">
            <i class="bi bi-person-vcard gm-kpi-ic" aria-hidden="true"></i>
            <div class="gm-kpi-val mt-2"><?= $e((string) ($roleCounts['client'] ?? 0)) ?></div>
            <div class="gm-kpi-lab"><?= $e($t('admin.users.kpi_clients')) ?></div>
        </div>
    </div>
</div>

<?php
// Role pill filters (Tutti + one per role, each with its real count).
$roleDots = ['admin' => 'primary', 'worker' => 'info', 'client' => 'success', 'subcontractor' => 'warning'];
$pills = [[
    'label'  => $t('common.all'),
    'href'   => $pillHref(''),
    'active' => $role === '',
    'count'  => $roleCounts['_total'] ?? 0,
]];
foreach ($roles as $r) {
    $pills[] = [
        'label'  => Lang::label('roles', $r),
        'href'   => $pillHref($r),
        'active' => $role === $r,
        'count'  => $roleCounts[$r] ?? 0,
        'dot'    => $roleDots[$r] ?? 'secondary',
    ];
}
echo View::render('partials/filter_pills', ['pills' => $pills], null);
?>

<div class="card app-filter-card mb-3">
    <div class="card-body">
        <form method="get" class="app-filter-grid app-filter-grid-2">
            <?php if ($role !== ''): ?>
                <input type="hidden" name="role" value="<?= $e($role) ?>">
            <?php endif; ?>
            <input type="text" class="form-control" name="q" value="<?= $e($search) ?>" placeholder="<?= $e($t('common.search')) ?>" aria-label="<?= $e($t('common.search')) ?>">
            <button type="submit" class="btn btn-success">
                <i class="bi bi-search" aria-hidden="true"></i> <?= $e($t('common.search')) ?>
            </button>
            <?= View::render('partials/filter_clear', [
                'active' => $search !== '' || $role !== '',
                'href'   => $role !== '' ? $pillHref($role) : '/admin/users',
                'inline' => true,
            ], null) ?>
        </form>
    </div>
</div>

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
                <?php $isActive = ((int) $u['is_active']) === 1; ?>
                <tr class="<?= $isActive ? '' : 'text-muted' ?>">
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="app-avatar"><?= $e($initials((string) $u['name'])) ?></span>
                            <a class="app-card-title-link fw-semibold" href="<?= $e(Url::to('/admin/users/' . $u['id'])) ?>"><?= $e($u['name']) ?></a>
                        </div>
                    </td>
                    <td><?= $e($u['email']) ?></td>
                    <td><span class="badge text-bg-light border"><?= $e(Lang::label('roles', $u['role'])) ?></span></td>
                    <td><?= $e($u['client_name'] ?? $u['subcontractor_name'] ?? '—') ?></td>
                    <td>
                        <?php if ($isActive): ?>
                            <span class="badge rounded-pill app-status app-status-success"><?= $e($t('admin.users.active')) ?></span>
                        <?php else: ?>
                            <span class="badge rounded-pill app-status app-status-neutral"><?= $e($t('admin.users.inactive')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/users/' . $u['id'] . '/edit')) ?>">
                            <?= $e($t('common.edit')) ?>
                        </a>
                        <?php if ((int) $u['id'] !== (int) ($user['id'] ?? 0)): ?>
                            <button type="button" class="btn btn-sm btn-outline-warning js-toggle-active"
                                    data-url="<?= $e(Url::to('/admin/users/' . $u['id'] . '/toggle')) ?>">
                                <?= $isActive ? $e($t('admin.users.deactivate')) : $e($t('admin.users.activate')) ?>
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
