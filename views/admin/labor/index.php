<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array{projects:array<int,array<string,mixed>>,people:array<int,array<string,mixed>>,totals:array{hours:float,cost:float},any_rate:bool} $labor */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$money = static fn (float $v): string => '€ ' . number_format($v, 2, ',', '.');
$hours = static fn (float $v): string => number_format($v, 1, ',', '.') . ' h';

$projects = $labor['projects'];
$people   = $labor['people'];
$tot      = $labor['totals'];

echo View::render('partials/page_head', [
    'title'    => $t('admin.labor.title'),
    'subtitle' => $t('admin.labor.subtitle'),
    'actions'  => '<a class="btn btn-outline-secondary" href="' . $e(Url::to('/admin/financials')) . '">'
        . '<i class="bi bi-graph-up-arrow" aria-hidden="true"></i> ' . $e($t('admin.labor.back_to_financials')) . '</a>',
], null);
?>

<?php if (!$labor['any_rate']): ?>
    <div class="alert alert-info d-flex align-items-start gap-2" role="status">
        <i class="bi bi-info-circle mt-1" aria-hidden="true"></i>
        <div><?= $e($t('admin.labor.no_rates_hint')) ?></div>
    </div>
<?php endif; ?>

<div class="app-kpi-grid mb-4">
    <div class="gm-kpi gm-kpi-solid is-info">
        <div class="gm-kpi-lab"><?= $e($t('admin.labor.total_hours')) ?></div>
        <div class="gm-kpi-val mt-1"><?= $e($hours((float) $tot['hours'])) ?></div>
    </div>
    <div class="gm-kpi gm-kpi-solid is-purple">
        <div class="gm-kpi-lab"><?= $e($t('admin.labor.total_cost')) ?></div>
        <div class="gm-kpi-val mt-1"><?= $e($money((float) $tot['cost'])) ?></div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header"><?= $e($t('admin.labor.by_project')) ?></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= $e($t('admin.labor.project')) ?></th>
                    <th class="text-end"><?= $e($t('admin.labor.hours')) ?></th>
                    <th class="text-end"><?= $e($t('admin.labor.cost')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($projects === []): ?>
                <tr><td colspan="3" class="text-center text-muted py-4"><?= $e($t('admin.labor.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($projects as $p): ?>
                <tr>
                    <td>
                        <a href="<?= $e(Url::to('/admin/projects/' . $p['id'])) ?>" class="app-card-title-link fw-semibold"><?= $e((string) $p['name']) ?></a>
                        <div class="small text-muted"><?= $e((string) $p['client_name']) ?></div>
                    </td>
                    <td class="text-end"><?= $e($hours((float) $p['hours'])) ?></td>
                    <td class="text-end fw-semibold"><?= $e($money((float) $p['cost'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header"><?= $e($t('admin.labor.by_person')) ?></div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= $e($t('admin.labor.person')) ?></th>
                    <th class="text-end d-none d-md-table-cell"><?= $e($t('admin.labor.rate')) ?></th>
                    <th class="text-end"><?= $e($t('admin.labor.hours')) ?></th>
                    <th class="text-end"><?= $e($t('admin.labor.cost')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($people === []): ?>
                <tr><td colspan="4" class="text-center text-muted py-4"><?= $e($t('admin.labor.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($people as $person): ?>
                <tr>
                    <td>
                        <span class="fw-semibold"><?= $e((string) $person['person_name']) ?></span>
                        <?php if ($person['is_subcontractor']): ?>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1"><?= $e((string) ($person['company_name'] ?? $t('admin.labor.subcontractor'))) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end d-none d-md-table-cell"><?= $person['rate'] > 0 ? $e($money((float) $person['rate']) . '/h') : '<span class="text-muted">' . $e($t('admin.labor.no_rate')) . '</span>' ?></td>
                    <td class="text-end"><?= $e($hours((float) $person['hours'])) ?></td>
                    <td class="text-end fw-semibold"><?= $e($money((float) $person['cost'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
