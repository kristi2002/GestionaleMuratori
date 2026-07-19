<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $plans */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$cadence = static function (array $plan) use ($t): string {
    $n = (int) $plan['interval_count'];
    if ($plan['frequency'] === 'monthly') {
        return $n === 1 ? $t('admin.recurring.cadence_monthly') : sprintf($t('admin.recurring.cadence_n_months'), $n);
    }
    return $n === 1 ? $t('admin.recurring.cadence_weekly') : sprintf($t('admin.recurring.cadence_n_weeks'), $n);
};

$actions = '<a class="btn btn-outline-secondary me-2" href="' . $e(Url::to('/admin/interventions')) . '">'
    . '<i class="bi bi-arrow-left" aria-hidden="true"></i> ' . $e($t('admin.interventions.title')) . '</a>'
    . '<a class="btn btn-success" href="' . $e(Url::to('/admin/interventions/recurring/create')) . '">'
    . '<i class="bi bi-plus-lg" aria-hidden="true"></i> ' . $e($t('admin.recurring.new')) . '</a>';

echo View::render('partials/page_head', [
    'title'    => $t('admin.recurring.title'),
    'subtitle' => $t('admin.recurring.subtitle'),
    'actions'  => $actions,
], null);
?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= $e($t('admin.recurring.plan')) ?></th>
                    <th class="d-none d-md-table-cell"><?= $e($t('admin.interventions.project')) ?></th>
                    <th><?= $e($t('admin.recurring.frequency')) ?></th>
                    <th class="d-none d-md-table-cell"><?= $e($t('admin.recurring.next_run')) ?></th>
                    <th><?= $e($t('admin.recurring.status')) ?></th>
                    <th style="width:8rem;"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($plans === []): ?>
                <tr><td colspan="6" class="text-center text-muted py-4"><?= $e($t('admin.recurring.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($plans as $plan): $active = (int) $plan['is_active'] === 1; ?>
                <tr class="<?= $active ? '' : 'opacity-50' ?>">
                    <td>
                        <a href="<?= $e(Url::to('/admin/interventions/recurring/' . $plan['id'] . '/edit')) ?>" class="app-card-title-link fw-semibold"><?= $e($plan['title']) ?></a>
                        <?php if (($plan['worker_name'] ?? null) !== null): ?>
                            <div class="small text-muted"><i class="bi bi-person" aria-hidden="true"></i> <?= $e($plan['worker_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-md-table-cell">
                        <?= $e($plan['project_name']) ?>
                        <div class="small text-muted"><?= $e($plan['client_name']) ?></div>
                    </td>
                    <td><?= $e($cadence($plan)) ?></td>
                    <td class="d-none d-md-table-cell"><?= $e((string) $plan['next_run_date']) ?></td>
                    <td>
                        <?php if ($active): ?>
                            <span class="badge bg-success-subtle text-success-emphasis"><?= $e($t('admin.recurring.active')) ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary-emphasis"><?= $e($t('admin.recurring.paused')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/interventions/recurring/' . $plan['id'] . '/edit')) ?>" aria-label="<?= $e($t('common.edit')) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a>
                            <button type="button" class="btn btn-outline-secondary js-toggle-active"
                                    data-url="<?= $e(Url::to('/admin/interventions/recurring/' . $plan['id'] . '/toggle')) ?>"
                                    aria-label="<?= $e($active ? $t('admin.recurring.pause') : $t('admin.recurring.resume')) ?>">
                                <i class="bi <?= $active ? 'bi-pause' : 'bi-play' ?>" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger js-crud-delete"
                                    data-url="<?= $e(Url::to('/admin/interventions/recurring/' . $plan['id'] . '/delete')) ?>"
                                    data-confirm="<?= $e($t('admin.recurring.delete_confirm')) ?>"
                                    aria-label="<?= $e($t('common.delete')) ?>"><i class="bi bi-trash" aria-hidden="true"></i></button>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
