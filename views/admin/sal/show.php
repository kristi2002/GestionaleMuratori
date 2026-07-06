<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed> $document */
/** @var array<int,array<string,mixed>> $lines */
/** @var array<int,array<string,mixed>> $items  warehouse items for line prefill */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$money = static fn ($v): string => number_format((float) $v, 2, ',', '.') . ' €';
$qty = static fn ($v): string => rtrim(rtrim((string) $v, '0'), '.');

$status  = $document['status'];
$isDraft = $status === 'draft';
$backUrl = Url::to('/admin/sal?project_id=' . $document['project_id']);
?>
<a href="<?= $e($backUrl) ?>" class="d-inline-block mb-3 small">&larr; <?= $e($t('admin.sal.back')) ?></a>

<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <h1 class="h5 mb-1"><?= $e($t('admin.sal.title')) ?> n. <?= $e((string) $document['number']) ?></h1>
                <p class="small text-muted mb-0"><?= $e($document['project_name']) ?> — <?= $e($document['client_name']) ?></p>
            </div>
            <span class="badge text-bg-light border"><?= $e(Lang::label('sal_status', $status)) ?></span>
        </div>
        <p class="h5 mt-2 mb-0"><?= $e($t('admin.sal.amount')) ?>: <strong><?= $e($money($document['amount'])) ?></strong></p>
    </div>
</div>

<?php if ($isDraft): ?>
    <div class="card mb-3">
        <div class="card-header bg-white"><?= $e($t('admin.sal.header')) ?></div>
        <div class="card-body">
            <form class="js-crud-form" data-base-url="<?= $e(Url::to('/admin/sal')) ?>">
                <div class="alert alert-danger d-none js-crud-error" role="alert"></div>
                <input type="hidden" name="id" value="<?= $e((string) $document['id']) ?>">
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label"><?= $e($t('admin.sal.period_from')) ?></label>
                        <input type="date" class="form-control" name="period_from" value="<?= $e($document['period_from']) ?>">
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label"><?= $e($t('admin.sal.period_to')) ?></label>
                        <input type="date" class="form-control" name="period_to" value="<?= $e($document['period_to']) ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= $e($t('admin.sal.description')) ?></label>
                    <textarea class="form-control" name="description" rows="2"><?= $e($document['description']) ?></textarea>
                </div>
                <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
            </form>
        </div>
    </div>
<?php elseif ($document['description']): ?>
    <div class="card mb-3"><div class="card-body"><?= nl2br($e($document['description'])) ?></div></div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header bg-white"><?= $e($t('admin.sal.lines')) ?></div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th><?= $e($t('admin.sal.line_description')) ?></th>
                    <th class="text-end"><?= $e($t('admin.sal.line_qty')) ?></th>
                    <th><?= $e($t('admin.sal.line_unit')) ?></th>
                    <th class="text-end"><?= $e($t('admin.sal.line_price')) ?></th>
                    <th class="text-end"><?= $e($t('admin.sal.line_amount')) ?></th>
                    <?php if ($isDraft): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if ($lines === []): ?>
                <tr><td colspan="<?= $isDraft ? 6 : 5 ?>" class="text-center text-muted py-3"><?= $e($t('admin.sal.no_lines')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($lines as $l): ?>
                <tr>
                    <td><?= $e($l['description']) ?></td>
                    <td class="text-end"><?= $e($qty($l['qty'])) ?></td>
                    <td><?= $e($l['unit']) ?></td>
                    <td class="text-end"><?= $e($money($l['unit_price'])) ?></td>
                    <td class="text-end"><?= $e($money($l['amount'])) ?></td>
                    <?php if ($isDraft): ?>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger js-crud-delete"
                                    data-url="<?= $e(Url::to('/admin/sal/' . $document['id'] . '/lines/' . $l['id'] . '/delete')) ?>"
                                    data-confirm="<?= $e($t('admin.sal.line_delete_confirm')) ?>">&times;</button>
                        </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($isDraft): ?>
        <div class="card-body border-top">
            <form class="js-crud-form" data-base-url="<?= $e(Url::to('/admin/sal/' . $document['id'] . '/lines')) ?>">
                <div class="alert alert-danger d-none js-crud-error" role="alert"></div>
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label small"><?= $e($t('admin.sal.from_item')) ?></label>
                        <select class="form-select form-select-sm" name="item_id">
                            <option value=""><?= $e($t('admin.sal.manual_line')) ?></option>
                            <?php foreach ($items as $it): ?>
                                <option value="<?= $e((string) $it['id']) ?>"><?= $e($it['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small"><?= $e($t('admin.sal.line_description')) ?></label>
                        <input type="text" class="form-control form-control-sm" name="description">
                    </div>
                    <div class="col-4 col-md-2">
                        <label class="form-label small"><?= $e($t('admin.sal.line_qty')) ?></label>
                        <input type="number" step="0.001" min="0" class="form-control form-control-sm" name="qty" required>
                    </div>
                    <div class="col-4 col-md-2">
                        <label class="form-label small"><?= $e($t('admin.sal.line_price')) ?></label>
                        <input type="number" step="0.0001" min="0" class="form-control form-control-sm" name="unit_price">
                    </div>
                    <div class="col-4 col-md-2">
                        <button type="submit" class="btn btn-sm btn-success w-100"><?= $e($t('admin.sal.add_line')) ?></button>
                    </div>
                </div>
                <div class="form-text"><?= $e($t('admin.sal.line_help')) ?></div>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php if ($isDraft): ?>
    <div class="card border-success">
        <div class="card-body">
            <p class="small text-muted mb-2"><?= $e($t('admin.sal.issue_help')) ?></p>
            <button type="button" class="btn btn-success js-crud-delete"
                    data-url="<?= $e(Url::to('/admin/sal/' . $document['id'] . '/issue')) ?>"
                    data-confirm="<?= $e($t('admin.sal.issue_confirm')) ?>">
                <?= $e($t('admin.sal.issue')) ?>
            </button>
        </div>
    </div>
<?php else: ?>
    <div class="card mb-3">
        <div class="card-body">
            <a class="btn btn-outline-success" href="<?= $e(Url::to('/admin/sal/' . $document['id'] . '/pdf')) ?>"><?= $e($t('admin.sal.download_pdf')) ?></a>
        </div>
    </div>

    <?php if ($status === 'issued'): ?>
        <div class="card mb-3">
            <div class="card-header bg-white"><?= $e($t('admin.sal.sign')) ?></div>
            <div class="card-body">
                <p class="small text-muted mb-2"><?= $e($t('admin.sal.sign_help')) ?></p>
                <div class="alert alert-danger d-none js-signature-error" role="alert"></div>
                <canvas id="signature-pad" class="border rounded w-100 bg-white" height="160" style="touch-action:none;"></canvas>
                <div class="mt-2 d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary js-signature-clear"><?= $e($t('worker.signature_clear')) ?></button>
                    <button type="button" class="btn btn-sm btn-success js-signature-save"
                            data-url="<?= $e(Url::to('/admin/sal/' . $document['id'] . '/sign')) ?>"
                            data-empty-message="<?= $e($t('admin.sal.signature_empty')) ?>">
                        <?= $e($t('admin.sal.sign_save')) ?>
                    </button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-success"><?= $e($t('admin.sal.signed_notice')) ?> (<?= $e(substr((string) $document['signed_at'], 0, 16)) ?>)</div>
    <?php endif; ?>
<?php endif; ?>
