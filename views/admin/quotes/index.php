<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $quotes Each row includes subtotal (lines only, VAT excluded). */
/** @var array<int,array<string,mixed>> $clients */
/** @var array{search:string,status:string,client_id:int} $filters */
/** @var array<int,string> $statuses */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$money = static fn ($v): string => '€ ' . number_format((float) $v, 2, ',', '.');
$date  = static fn (?string $v): string => $v ? date('d/m/Y', (int) strtotime($v)) : '—';
$badge = [
    'draft'    => 'text-bg-secondary',
    'sent'     => 'text-bg-primary',
    'accepted' => 'text-bg-success',
    'rejected' => 'text-bg-danger',
    'expired'  => 'text-bg-warning',
];
?>
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.quotes.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.quotes.subtitle')) ?></p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a class="btn btn-success" href="<?= $e(Url::to('/admin/quotes/create')) ?>">
            <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= $e($t('admin.quotes.new')) ?>
        </a>
        <?= View::render('partials/back_button', ['href' => '/admin'], null) ?>
    </div>
</div>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.quotes.title'), null],
]], null) ?>

<div class="card app-filter-card mb-3">
    <div class="card-body">
        <form method="get" class="app-filter-grid app-filter-grid-4">
            <input type="text" class="form-control" name="q" value="<?= $e($filters['search']) ?>"
                   placeholder="<?= $e($t('admin.quotes.search_placeholder')) ?>" aria-label="<?= $e($t('common.search')) ?>">
            <select class="form-select" name="status" aria-label="<?= $e($t('admin.quotes.status')) ?>">
                <option value=""><?= $e($t('common.all')) ?> — <?= $e($t('admin.quotes.status')) ?></option>
                <?php foreach ($statuses as $s): ?>
                    <option value="<?= $e($s) ?>" <?= $filters['status'] === $s ? 'selected' : '' ?>>
                        <?= $e(Lang::label('quote_status', $s)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select class="form-select" name="client_id" aria-label="<?= $e($t('admin.clients.title')) ?>">
                <option value=""><?= $e($t('common.all')) ?> — <?= $e($t('admin.clients.title')) ?></option>
                <?php foreach ($clients as $c): ?>
                    <option value="<?= $e((string) $c['id']) ?>" <?= $filters['client_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                        <?= $e($c['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-success">
                <i class="bi bi-search" aria-hidden="true"></i> <?= $e($t('common.search')) ?>
            </button>
        </form>
        <?= View::render('partials/filter_clear', [
            'active' => $filters['search'] !== '' || $filters['status'] !== '' || $filters['client_id'] > 0,
            'href'   => '/admin/quotes',
        ], null) ?>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= $e($t('admin.quotes.number')) ?></th>
                    <th><?= $e($t('admin.quotes.field_title')) ?></th>
                    <th><?= $e($t('admin.projects.client')) ?></th>
                    <th><?= $e($t('admin.quotes.date')) ?></th>
                    <th><?= $e($t('admin.quotes.valid_until')) ?></th>
                    <th class="text-end"><?= $e($t('admin.quotes.total')) ?></th>
                    <th><?= $e($t('admin.quotes.status')) ?></th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($quotes === []): ?>
                <tr>
                    <td colspan="8">
                        <div class="app-empty-state py-4">
                            <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                            <p class="mb-1 fw-semibold"><?= $e($t('admin.quotes.empty')) ?></p>
                            <p class="small mb-3"><?= $e($t('common.no_results_hint')) ?></p>
                            <a class="btn btn-outline-secondary btn-sm" href="<?= $e(Url::to('/admin/quotes')) ?>">
                                <?= $e($t('common.reset_filters')) ?>
                            </a>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
            <?php foreach ($quotes as $q): ?>
                <?php $total = (float) $q['subtotal'] * (1 + (float) $q['vat_rate'] / 100); ?>
                <tr>
                    <td class="fw-semibold"><?= $e($q['number']) ?></td>
                    <td>
                        <?= $e($q['title']) ?>
                        <?php if ($q['project_name']): ?>
                            <div class="small text-muted"><?= $e($q['project_name']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= $e($q['client_name']) ?></td>
                    <td><?= $e($date($q['quote_date'])) ?></td>
                    <td><?= $e($date($q['valid_until'])) ?></td>
                    <td class="text-end"><?= $e($money($total)) ?></td>
                    <td>
                        <span class="badge <?= $e($badge[$q['status']] ?? 'text-bg-secondary') ?>">
                            <?= $e(Lang::label('quote_status', $q['status'])) ?>
                        </span>
                    </td>
                    <td class="text-end text-nowrap">
                        <?php if ($q['status'] === 'accepted' && $q['project_name']): ?>
                            <button type="button" class="btn btn-sm btn-outline-success js-post-action"
                                    data-url="<?= $e(Url::to('/admin/quotes/' . $q['id'] . '/invoice')) ?>"
                                    data-confirm="<?= $e($t('admin.quotes.to_invoice_confirm')) ?>"
                                    data-ok-label="<?= $e($t('admin.quotes.to_invoice')) ?>">
                                <i class="bi bi-receipt" aria-hidden="true"></i> <?= $e($t('admin.quotes.to_invoice')) ?>
                            </button>
                        <?php endif; ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/quotes/' . $q['id'] . '/pdf')) ?>">
                            <i class="bi bi-printer" aria-hidden="true"></i> PDF
                        </a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/quotes/' . $q['id'] . '/edit')) ?>">
                            <?= $e($t('common.edit')) ?>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger js-crud-delete"
                                data-url="<?= $e(Url::to('/admin/quotes/' . $q['id'] . '/delete')) ?>"
                                data-confirm="<?= $e($t('admin.quotes.delete_confirm')) ?>">
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
