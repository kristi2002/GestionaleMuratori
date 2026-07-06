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
$salPill = static fn (string $s): string
    => ['draft' => 'text-bg-secondary', 'issued' => 'text-bg-info', 'signed' => 'text-bg-success'][$s] ?? 'text-bg-secondary';
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.sal.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.sal.subtitle')) ?></p>
    </div>
    <?php if ($projectId > 0): ?>
        <button type="button" class="btn btn-success js-crud-new" data-bs-toggle="modal" data-bs-target="#sal-modal" data-target-modal="#sal-modal">
            <?= $e($t('admin.sal.new')) ?>
        </button>
    <?php endif; ?>
</div>

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
                    <td><span class="badge <?= $e($salPill($d['status'])) ?>"><?= $e(Lang::label('sal_status', $d['status'])) ?></span></td>
                    <td class="text-end">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= $e(Url::to('/admin/sal/' . $d['id'])) ?>"><?= $e($t('admin.sal.open')) ?></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="sal-modal" tabindex="-1" data-title-create="<?= $e($t('admin.sal.new')) ?>" data-title-edit="<?= $e($t('admin.sal.new')) ?>">
    <div class="modal-dialog">
        <div class="modal-content">
            <form class="js-crud-form" data-base-url="<?= $e(Url::to('/admin/sal')) ?>">
                <div class="modal-header">
                    <h2 class="modal-title h5"><?= $e($t('admin.sal.new')) ?></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger d-none js-crud-error" role="alert"></div>
                    <input type="hidden" name="project_id" value="<?= $e((string) $projectId) ?>">
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label"><?= $e($t('admin.sal.period_from')) ?></label>
                            <input type="date" class="form-control" name="period_from">
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label"><?= $e($t('admin.sal.period_to')) ?></label>
                            <input type="date" class="form-control" name="period_to">
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label"><?= $e($t('admin.sal.description')) ?></label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= $e($t('common.cancel')) ?></button>
                    <button type="submit" class="btn btn-success"><?= $e($t('common.create')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
