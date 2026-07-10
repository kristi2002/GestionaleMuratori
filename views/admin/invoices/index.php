<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $invoices */
/** @var array<int,array<string,mixed>> $projects */
/** @var array{search:string,status:string,project_id:int} $filters */
/** @var array<int,string> $statuses */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$money = static fn ($v): string => $v === null ? '—' : '€ ' . number_format((float) $v, 2, ',', '.');
$date  = static fn (?string $v): string => $v ? date('d/m/Y', (int) strtotime($v)) : '—';
$badge = ['draft' => 'text-bg-secondary', 'issued' => 'text-bg-primary', 'paid' => 'text-bg-success'];
?>
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.invoices.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.invoices.subtitle')) ?></p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a class="btn btn-success" href="<?= $e(Url::to('/admin/invoices/create')) ?>">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= $e($t('admin.invoices.new')) ?>
        </a>
        <button type="button" class="btn btn-outline-secondary" disabled
                title="<?= $e($t('nav.coming_soon')) ?>">
            <i class="bi bi-lightning-charge" aria-hidden="true"></i> <?= $e($t('admin.invoices.einvoice_soon')) ?>
        </button>
        <?= View::render('partials/back_button', ['href' => '/admin'], null) ?>
    </div>
</div>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.invoices.title'), null],
]], null) ?>

<div class="card app-filter-card mb-3">
    <div class="card-body">
        <form method="get" class="app-filter-grid app-filter-grid-4">
            <input type="text" class="form-control" name="q" value="<?= $e($filters['search']) ?>"
                   placeholder="<?= $e($t('admin.invoices.search_placeholder')) ?>" aria-label="<?= $e($t('common.search')) ?>">
            <select class="form-select" name="status" aria-label="<?= $e($t('admin.projects.invoice_status')) ?>">
                <option value=""><?= $e($t('common.all')) ?> — <?= $e($t('admin.projects.invoice_status')) ?></option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= $e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>>
                        <?= $e(Lang::label('invoice_status', $s)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="project_id" aria-label="<?= $e($t('admin.projects.title')) ?>">
                <option value=""><?= $e($t('common.all')) ?> — <?= $e($t('admin.projects.title')) ?></option>
                <?php foreach ($projects as $p): ?>
                    <option value="<?= $e((string) $p['id']) ?>" <?= $filters['project_id'] === (int) $p['id'] ? 'selected' : '' ?>>
                        <?= $e($p['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-search" aria-hidden="true"></i> <?= $e($t('common.search')) ?>
            </button>
        </form>
        <?= View::render('partials/filter_clear', [
            'active' => $filters['search'] !== '' || $filters['status'] !== '' || $filters['project_id'] > 0,
            'href'   => '/admin/invoices',
        ], null) ?>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= $e($t('admin.projects.invoice_number')) ?></th>
                    <th><?= $e($t('admin.projects.invoice_date')) ?></th>
                    <th><?= $e($t('admin.projects.client')) ?></th>
                    <th><?= $e($t('admin.interventions.project')) ?></th>
                    <th class="text-end"><?= $e($t('admin.projects.invoice_amount')) ?></th>
                    <th><?= $e($t('admin.projects.invoice_status')) ?></th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($invoices === []): ?>
                <tr>
                    <td colspan="7">
                        <div class="app-empty-state py-4">
                            <i class="bi bi-receipt" aria-hidden="true"></i>
                            <p class="mb-1 fw-semibold"><?= $e($t('admin.invoices.empty')) ?></p>
                            <p class="small mb-3"><?= $e($t('common.no_results_hint')) ?></p>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= $e(Url::to('/admin/invoices')) ?>">
                                <?= $e($t('common.reset_filters')) ?>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($invoices as $inv): ?>
                <tr>
                    <td class="fw-semibold"><?= $e($inv['number']) ?></td>
                    <td><?= $e($date($inv['issue_date'])) ?></td>
                    <td><?= $e($inv['client_name']) ?></td>
                    <td><?= $e($inv['project_name']) ?></td>
                    <td class="text-end"><?= $e($money($inv['amount'])) ?></td>
                    <td>
                        <span class="badge <?= $e($badge[$inv['status']] ?? 'text-bg-secondary') ?>">
                            <?= $e(Lang::label('invoice_status', $inv['status'])) ?>
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/invoices/' . $inv['id'] . '/print')) ?>">
                            <i class="bi bi-printer" aria-hidden="true"></i> <?= $e($t('admin.invoices.print')) ?>
                        </a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/invoices/' . $inv['id'] . '/edit')) ?>">
                            <?= $e($t('common.edit')) ?>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger js-crud-delete"
                                data-url="<?= $e(Url::to('/admin/invoices/' . $inv['id'] . '/delete')) ?>"
                                data-confirm="<?= $e($t('admin.projects.invoice_delete_confirm')) ?>">
                            <?= $e($t('common.delete')) ?>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isset($paginator)) { echo View::render('partials/pagination', ['paginator' => $paginator], null); } ?>
