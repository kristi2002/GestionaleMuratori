<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $projects */
/** @var int $projectId */
/** @var array<int,array<string,mixed>> $documents */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$money = static fn ($v): string => number_format((float) $v, 2, ',', '.') . ' €';

// Real aggregates over the already-loaded per-project S.A.L. list (no extra query).
$totalCount  = count($documents);
$statusCount = ['draft' => 0, 'issued' => 0, 'signed' => 0];
$totalValue  = 0.0;
foreach ($documents as $d) {
    $st = (string) $d['status'];
    if (isset($statusCount[$st])) { $statusCount[$st]++; }
    $totalValue += (float) $d['amount'];
}

$actions = '';
if ($projectId > 0) {
    $actions = '<a class="btn btn-success" href="' . $e(Url::to('/admin/sal/create?project_id=' . $projectId)) . '">'
        . '<i class="bi bi-plus-lg" aria-hidden="true"></i> ' . $e($t('admin.sal.new')) . '</a>';
}

echo View::render('partials/page_head', [
    'title'    => $t('admin.sal.title'),
    'subtitle' => $t('admin.sal.subtitle'),
    'actions'  => $actions,
], null);
?>

<?php if ($documents !== []): ?>
<div class="row g-3 mb-3">
    <?php
    $kpis = [
        ['is-primary', 'bi-journals',      $t('admin.sal.kpi_total'),                (string) $totalCount],
        ['is-info',    'bi-send',          Lang::label('sal_status', 'issued'),      (string) $statusCount['issued']],
        ['ok',         'bi-patch-check',   Lang::label('sal_status', 'signed'),      (string) $statusCount['signed']],
        ['',           'bi-cash-stack',    $t('admin.sal.kpi_value'),                $money($totalValue)],
    ];
    foreach ($kpis as [$variant, $icon, $label, $val]): ?>
        <div class="col-6 col-lg-3">
            <div class="card gm-kpi h-100<?= $variant !== '' ? ' ' . $variant : '' ?>">
                <div class="card-body">
                    <i class="bi <?= $e($icon) ?> gm-kpi-ic" aria-hidden="true"></i>
                    <div class="gm-kpi-val mt-2"><?= $e($val) ?></div>
                    <div class="gm-kpi-lab"><?= $e($label) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="get" class="row g-2 mb-3">
    <div class="col-12 col-sm-6">
        <select class="form-select" name="project_id" onchange="this.form.submit()">
            <?php foreach ($projects as $p): ?>
                <option value="<?= $e((string) $p['id']) ?>" <?= $projectId === (int) $p['id'] ? 'selected' : '' ?>>
                    <?= $e($p['name']) ?> — <?= $e($p['client_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= $e($t('admin.sal.number')) ?></th>
                    <th><?= $e($t('admin.sal.period')) ?></th>
                    <th><?= $e($t('admin.sal.amount')) ?></th>
                    <th><?= $e($t('admin.sal.status')) ?></th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($documents === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?= $e($t('admin.sal.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($documents as $d): ?>
                <tr>
                    <td class="mono fw-bold">#<?= $e((string) $d['number']) ?></td>
                    <td class="mono tnum"><?= $e($d['period_from'] ?? '—') ?><?= $d['period_to'] ? ' — ' . $e($d['period_to']) : '' ?></td>
                    <td class="mono tnum"><?= $e($money($d['amount'])) ?></td>
                    <td><?= View::render('partials/status_badge', ['group' => 'sal_status', 'value' => (string) $d['status']], null) ?></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/sal/' . $d['id'])) ?>"><?= $e($t('admin.sal.open')) ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
