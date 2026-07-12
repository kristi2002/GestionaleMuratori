<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $interventions */
/** @var array<int,array<string,mixed>> $projects */
/** @var array<int,array<string,mixed>> $workers */
/** @var array<int,array<string,mixed>> $warehouseItems */
/** @var array{project_id:int,worker_id:int,status:string} $filters */
/** @var array<int,string> $statuses */
/** @var string $range */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$rangeLink = static function (string $value) use ($filters): string {
    $query = array_filter([
        'project_id' => $filters['project_id'] ?: null,
        'worker_id'  => $filters['worker_id'] ?: null,
        'status'     => $filters['status'] ?: null,
        'range'      => $value ?: null,
    ]);
    return Url::to('/admin/interventions') . ($query !== [] ? '?' . http_build_query($query) : '');
};

/** Status transitions an admin can trigger from the list (completed is worker-only, Phase 5). */
$nextActions = [
    'pending'     => [['to' => 'in_progress', 'label' => $t('admin.interventions.start')], ['to' => 'cancelled', 'label' => $t('admin.interventions.cancel')]],
    'in_progress' => [['to' => 'on_hold', 'label' => $t('admin.interventions.hold')], ['to' => 'cancelled', 'label' => $t('admin.interventions.cancel')]],
    'on_hold'     => [['to' => 'in_progress', 'label' => $t('admin.interventions.resume')], ['to' => 'cancelled', 'label' => $t('admin.interventions.cancel')]],
];
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.interventions.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.interventions.subtitle')) ?></p>
    </div>
    <a class="btn btn-success" href="<?= $e(Url::to('/admin/interventions/create')) ?>">
        <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= $e($t('admin.interventions.new')) ?>
    </a>
</div>

<div class="card app-filter-card mb-3">
    <div class="card-body">
        <div class="btn-group btn-group-sm mb-3" role="group" aria-label="<?= $e($t('admin.interventions.title')) ?>">
            <a class="btn <?= $range === 'today' ? 'btn-success' : 'btn-outline-secondary' ?>" href="<?= $e($rangeLink('today')) ?>"><?= $e($t('admin.interventions.range_today')) ?></a>
            <a class="btn <?= $range === 'week' ? 'btn-success' : 'btn-outline-secondary' ?>" href="<?= $e($rangeLink('week')) ?>"><?= $e($t('admin.interventions.range_week')) ?></a>
            <a class="btn <?= $range === '' ? 'btn-success' : 'btn-outline-secondary' ?>" href="<?= $e($rangeLink('')) ?>"><?= $e($t('admin.interventions.range_all')) ?></a>
        </div>
        <form method="get" class="app-filter-grid app-filter-grid-selects">
            <input type="hidden" name="range" value="<?= $e($range) ?>">
            <select class="form-select" name="project_id" aria-label="<?= $e($t('admin.interventions.project')) ?>">
                <option value=""><?= $e($t('admin.interventions.project')) ?> — <?= $e($t('common.all')) ?></option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= $e((string) $p['id']) ?>" <?= ((int) $filters['project_id'] === (int) $p['id']) ? 'selected' : '' ?>><?= $e($p['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="worker_id" aria-label="<?= $e($t('admin.interventions.worker')) ?>">
                <option value=""><?= $e($t('admin.interventions.worker')) ?> — <?= $e($t('common.all')) ?></option>
                <?php foreach ($workers as $w): ?>
                    <option value="<?= $e((string) $w['id']) ?>" <?= ((int) $filters['worker_id'] === (int) $w['id']) ? 'selected' : '' ?>><?= $e($w['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="status" aria-label="<?= $e($t('admin.interventions.status')) ?>">
                <option value=""><?= $e($t('admin.interventions.status')) ?> — <?= $e($t('common.all')) ?></option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= $e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>><?= $e(Lang::label('intervention_status', $s)) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-search" aria-hidden="true"></i> <?= $e($t('common.search')) ?>
            </button>
            <?= View::render('partials/filter_clear', [
                'active' => $filters['project_id'] > 0 || $filters['worker_id'] > 0 || $filters['status'] !== '',
                'href'   => '/admin/interventions' . ($range !== '' ? '?range=' . $range : ''),
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
                    <th><?= $e($t('admin.interventions.field_title')) ?></th>
                    <th><?= $e($t('admin.interventions.project')) ?></th>
                    <th><?= $e($t('admin.interventions.worker')) ?></th>
                    <th><?= $e($t('admin.interventions.scheduled_date')) ?></th>
                    <th><?= $e($t('admin.interventions.materials')) ?></th>
                    <th><?= $e($t('admin.interventions.status')) ?></th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($interventions === []): ?>
                <tr><td colspan="7" class="text-center text-muted py-4"><?= $e($t('admin.interventions.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($interventions as $iv): ?>
                <tr>
                    <td>
                        <a href="<?= $e(Url::to('/admin/interventions/' . $iv['id'])) ?>" class="text-decoration-none"><?= $e($iv['title']) ?></a>
                        <div class="small text-muted"><?= $e($iv['client_name']) ?></div>
                    </td>
                    <td><?= $e($iv['project_name']) ?></td>
                    <td><?= $e($iv['worker_name'] ?? $t('admin.interventions.unassigned')) ?></td>
                    <td class="small">
                        <?= $e($iv['scheduled_date']) ?>
                        <?= $iv['scheduled_start_time'] ? ' ' . $e(substr((string) $iv['scheduled_start_time'], 0, 5)) : '' ?>
                    </td>
                    <td class="small">
                        <?php if ($iv['materials'] === []): ?>
                            <span class="text-muted">—</span>
                        <?php else: ?>
                            <?php foreach ($iv['materials'] as $m): ?>
                                <div><?= $e($m['item_name']) ?>: <?= $e(rtrim(rtrim((string) $m['qty_planned'], '0'), '.')) ?> <?= $e(Lang::label('units', $m['unit'])) ?></div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge text-bg-light border"><?= $e(Lang::label('intervention_status', $iv['status'])) ?></span></td>
                    <td class="text-end app-row-actions">
                        <div class="d-inline-flex flex-nowrap gap-1 align-items-center">
                            <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/interventions/' . $iv['id'] . '/edit')) ?>">
                                <?= $e($t('common.edit')) ?>
                            </a>
                            <?php foreach ($nextActions[$iv['status']] ?? [] as $action): ?>
                                <button type="button" class="btn btn-sm <?= $action['to'] === 'cancelled' ? 'btn-outline-danger' : 'btn-outline-success' ?> js-intervention-status"
                                        data-url="<?= $e(Url::to('/admin/interventions/' . $iv['id'] . '/status')) ?>"
                                        data-to-status="<?= $e($action['to']) ?>"
                                        <?= $action['to'] === 'cancelled' ? 'data-confirm="' . $e($t('admin.interventions.cancel_confirm')) . '"' : '' ?>>
                                    <?= $e($action['label']) ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isset($paginator)) { echo View::render('partials/pagination', ['paginator' => $paginator], null); } ?>
