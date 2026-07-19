<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed> $lead */
/** @var array<string,mixed>|null $client */
/** @var array<int,string> $statuses */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $k): string => Lang::get($k);

$badge = ['new' => 'warning', 'contacted' => 'info', 'converted' => 'success', 'archived' => 'secondary'];
$status = (string) $lead['status'];

echo View::render('partials/page_head', [
    'title'    => (string) $lead['name'],
    'subtitle' => $t('admin.leads.subtitle'),
    'actions'  => View::render('partials/back_button', ['href' => '/admin/leads'], null),
], null);
?>

<div class="app-cols">
    <div>
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><?= $e($t('admin.leads.detail')) ?></span>
                <span class="badge bg-<?= $e($badge[$status] ?? 'secondary') ?>-subtle text-<?= $e($badge[$status] ?? 'secondary') ?>-emphasis">
                    <?= $e(Lang::label('lead_status', $status)) ?>
                </span>
            </div>
            <div class="card-body">
                <dl class="app-dl mb-0">
                    <div class="app-dl-row"><dt><?= $e($t('admin.leads.name')) ?></dt><dd><?= $e($lead['name']) ?></dd></div>
                    <?php if (($lead['email'] ?? null) !== null && $lead['email'] !== ''): ?>
                        <div class="app-dl-row"><dt><?= $e($t('admin.leads.email')) ?></dt>
                            <dd><a href="mailto:<?= $e($lead['email']) ?>"><?= $e($lead['email']) ?></a></dd></div>
                    <?php endif; ?>
                    <?php if (($lead['phone'] ?? null) !== null && $lead['phone'] !== ''): ?>
                        <div class="app-dl-row"><dt><?= $e($t('admin.leads.phone')) ?></dt>
                            <dd><a href="tel:<?= $e($lead['phone']) ?>"><?= $e($lead['phone']) ?></a></dd></div>
                    <?php endif; ?>
                    <div class="app-dl-row"><dt><?= $e($t('admin.leads.received')) ?></dt><dd><?= $e(substr((string) $lead['created_at'], 0, 16)) ?></dd></div>
                    <?php if (($lead['message'] ?? null) !== null && $lead['message'] !== ''): ?>
                        <div class="app-dl-row"><dt><?= $e($t('admin.leads.message')) ?></dt><dd><?= nl2br($e($lead['message'])) ?></dd></div>
                    <?php endif; ?>
                </dl>

                <?php if ($client !== null): ?>
                    <div class="alert alert-success mt-3 mb-0 d-flex align-items-center justify-content-between gap-2">
                        <span><i class="bi bi-check-circle" aria-hidden="true"></i> <?= $e($t('admin.leads.converted_to')) ?> <strong><?= $e($client['name']) ?></strong></span>
                        <a class="btn btn-sm btn-outline-success" href="<?= $e(Url::to('/admin/clients/' . $client['id'])) ?>"><?= $e($t('admin.leads.open_client')) ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="app-rail">
        <div class="app-rail-card">
            <h2 class="app-rail-title"><?= $e($t('admin.leads.actions')) ?></h2>

            <?php if ($status !== 'converted'): ?>
                <div class="d-grid gap-2 mb-3">
                    <button type="button" class="btn btn-success js-post-action"
                            data-url="<?= $e(Url::to('/admin/leads/' . $lead['id'] . '/convert')) ?>"
                            data-confirm="<?= $e($t('admin.leads.convert_confirm')) ?>"
                            data-ok-label="<?= $e($t('admin.leads.convert')) ?>">
                        <i class="bi bi-person-plus" aria-hidden="true"></i> <?= $e($t('admin.leads.convert')) ?>
                    </button>
                </div>
            <?php endif; ?>

            <div class="small text-muted mb-1"><?= $e($t('admin.leads.set_status')) ?></div>
            <div class="d-grid gap-2 mb-3">
                <?php foreach (['new', 'contacted', 'archived'] as $s): ?>
                    <?php if ($s !== $status): ?>
                        <button type="button" class="btn btn-sm btn-outline-secondary js-lead-status"
                                data-url="<?= $e(Url::to('/admin/leads/' . $lead['id'] . '/status')) ?>"
                                data-status="<?= $e($s) ?>">
                            <?= $e(Lang::label('lead_status', $s)) ?>
                        </button>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>

            <div class="pt-3 border-top">
                <button type="button" class="btn btn-sm btn-outline-danger w-100 js-crud-delete"
                        data-url="<?= $e(Url::to('/admin/leads/' . $lead['id'] . '/delete')) ?>"
                        data-confirm="<?= $e($t('admin.leads.delete_confirm')) ?>"
                        data-redirect="<?= $e(Url::to('/admin/leads')) ?>">
                    <i class="bi bi-trash" aria-hidden="true"></i> <?= $e($t('common.delete')) ?>
                </button>
            </div>
        </div>
    </div>
</div>
