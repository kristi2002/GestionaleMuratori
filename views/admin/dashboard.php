<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var int $activeProjects */
/** @var int $openInterventions */
/** @var array<string,int> $todayByStatus */
/** @var array<int,array<string,mixed>> $lowStock */
/** @var array|null $user */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$qty = static fn ($v): string => rtrim(rtrim((string) $v, '0'), '.');

$todayTotal = array_sum($todayByStatus);
?>
<h1 class="h4 mb-1"><?= $e($t('admin.dashboard.title')) ?></h1>
<p class="text-muted mb-3"><?= $e($t('admin.dashboard.welcome')) ?> <?= $e($user['name'] ?? '') ?>.</p>

<div class="row g-3">
    <div class="col-6 col-lg-3">
        <a class="card text-decoration-none h-100" href="<?= $e(Url::to('/admin/projects?status=active')) ?>">
            <div class="card-body">
                <div class="display-6 fw-bold text-success"><?= $e((string) $activeProjects) ?></div>
                <div class="small text-muted"><?= $e($t('admin.dashboard.active_projects')) ?></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a class="card text-decoration-none h-100" href="<?= $e(Url::to('/admin/interventions')) ?>">
            <div class="card-body">
                <div class="display-6 fw-bold text-success"><?= $e((string) $openInterventions) ?></div>
                <div class="small text-muted"><?= $e($t('admin.dashboard.open_interventions')) ?></div>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a class="card text-decoration-none h-100" href="<?= $e(Url::to('/admin/interventions?range=today')) ?>">
            <div class="card-body">
                <div class="display-6 fw-bold text-success"><?= $e((string) $todayTotal) ?></div>
                <div class="small text-muted"><?= $e($t('admin.dashboard.today_interventions')) ?></div>
                <?php if ($todayTotal > 0): ?>
                    <div class="small mt-1">
                        <?php foreach ($todayByStatus as $status => $n): ?>
                            <span class="badge text-bg-light border me-1"><?= $e(Lang::label('intervention_status', $status)) ?>: <?= $e((string) $n) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </a>
    </div>
    <div class="col-6 col-lg-3">
        <a class="card text-decoration-none h-100 <?= $lowStock !== [] ? 'border-danger' : '' ?>" href="<?= $e(Url::to('/admin/warehouse')) ?>">
            <div class="card-body">
                <div class="display-6 fw-bold <?= $lowStock !== [] ? 'text-danger' : 'text-success' ?>"><?= $e((string) count($lowStock)) ?></div>
                <div class="small text-muted"><?= $e($t('admin.dashboard.low_stock')) ?></div>
            </div>
        </a>
    </div>
</div>

<?php if ($lowStock !== []): ?>
    <div class="card mt-3 border-danger">
        <div class="card-header bg-white text-danger"><?= $e($t('admin.dashboard.low_stock_title')) ?></div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= $e($t('admin.warehouse.name')) ?></th>
                        <th><?= $e($t('admin.warehouse.qty_in_stock')) ?></th>
                        <th><?= $e($t('admin.warehouse.reorder_level')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($lowStock as $item): ?>
                    <tr>
                        <td><?= $e($item['name']) ?></td>
                        <td class="text-danger fw-bold"><?= $e($qty($item['qty_in_stock'])) ?> <?= $e(Lang::label('units', $item['unit'])) ?></td>
                        <td><?= $e($qty($item['reorder_level'])) ?> <?= $e(Lang::label('units', $item['unit'])) ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/warehouse/' . $item['id'])) ?>"><?= $e($t('admin.dashboard.open')) ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<h2 class="h6 text-muted mt-4 mb-2"><?= $e($t('admin.dashboard.sections')) ?></h2>
<div class="row g-3">
    <?php
    $cards = [
        [$t('admin.clients.title'), $t('admin.clients.subtitle'), '/admin/clients'],
        [$t('admin.projects.title'), $t('admin.projects.subtitle'), '/admin/projects'],
        [$t('admin.warehouse.title'), $t('admin.warehouse.subtitle'), '/admin/warehouse'],
        [$t('admin.interventions.title'), $t('admin.interventions.subtitle'), '/admin/interventions'],
        [$t('admin.users.title'), $t('admin.users.subtitle'), '/admin/users'],
        [$t('admin.dashboard.reports'), $t('admin.dashboard.reports_subtitle'), '/admin/projects'],
    ];
    foreach ($cards as [$titleCard, $descCard, $href]):
    ?>
        <div class="col-12 col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <h3 class="h6 mb-1"><?= $e($titleCard) ?></h3>
                    <p class="small text-muted mb-2"><?= $e($descCard) ?></p>
                    <a class="btn btn-sm btn-success" href="<?= $e(Url::to($href)) ?>"><?= $e($t('admin.dashboard.open')) ?></a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
