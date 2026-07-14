<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed> $intervention */
/** @var array<int,array<string,mixed>> $materials */
/** @var array<int,array<string,mixed>> $history */
/** @var array{before:array,during:array,after:array} $photosByType */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$qty = static fn ($v): string => $v !== null ? rtrim(rtrim((string) $v, '0'), '.') : '—';

// Compact initials avatar from a display name (same pattern as projects/index.php).
$initials = static function (string $name): string {
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $ini = '';
    foreach ($parts as $p) {
        if ($p !== '') { $ini .= mb_strtoupper(mb_substr($p, 0, 1)); }
        if (mb_strlen($ini) >= 2) { break; }
    }
    return $ini !== '' ? $ini : '—';
};

$status = (string) $intervention['status'];

$nextActions = [
    'pending'     => [['to' => 'in_progress', 'label' => $t('admin.interventions.start')], ['to' => 'cancelled', 'label' => $t('admin.interventions.cancel')]],
    'in_progress' => [['to' => 'on_hold', 'label' => $t('admin.interventions.hold')], ['to' => 'cancelled', 'label' => $t('admin.interventions.cancel')]],
    'on_hold'     => [['to' => 'in_progress', 'label' => $t('admin.interventions.resume')], ['to' => 'cancelled', 'label' => $t('admin.interventions.cancel')]],
];

$actions = '<a class="btn btn-outline-secondary" href="' . $e(Url::to('/admin/interventions/' . $intervention['id'] . '/edit')) . '">'
    . '<i class="bi bi-pencil" aria-hidden="true"></i> ' . $e($t('common.edit')) . '</a>'
    . View::render('partials/back_button', ['href' => '/admin/interventions'], null);

echo View::render('partials/page_head', [
    'title'    => (string) $intervention['title'],
    'subtitle' => $intervention['project_name'] . ' — ' . $intervention['client_name'],
    'actions'  => $actions,
], null);
?>

<div class="app-cols">
    <div>
        <?php if ($intervention['description']): ?>
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="app-rail-title"><?= $e($t('admin.interventions.description')) ?></h2>
                    <p class="mb-0"><?= nl2br($e($intervention['description'])) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-header"><?= $e($t('admin.interventions.materials')) ?></div>
            <div class="card-body">
                <?php if ($materials === []): ?>
                    <p class="text-muted small mb-0"><?= $e($t('worker.no_materials')) ?></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th><?= $e($t('admin.interventions.item')) ?></th>
                                    <th><?= $e($t('worker.qty_planned')) ?></th>
                                    <th><?= $e($t('worker.qty_used')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($materials as $m): ?>
                                <tr>
                                    <td><?= $e($m['item_name']) ?> <span class="text-muted small">(<?= $e(Lang::label('units', $m['unit'])) ?>)</span></td>
                                    <td><?= $e($qty($m['qty_planned'])) ?></td>
                                    <td><?= $e($qty($m['qty_used'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php foreach (['before', 'during', 'after'] as $type): ?>
            <?php if ($photosByType[$type] !== []): ?>
                <div class="card mb-3">
                    <div class="card-header"><?= $e($t('worker.photos')) ?> — <?= $e(Lang::label('photo_types', $type)) ?></div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($photosByType[$type] as $photo): ?>
                                <div class="text-center">
                                    <a href="<?= $e(Url::to('/admin/photos/' . $photo['id'])) ?>" target="_blank" rel="noopener">
                                        <img src="<?= $e(Url::to('/admin/photos/' . $photo['id'] . '/thumb')) ?>" alt=""
                                             class="rounded border" style="width:88px;height:88px;object-fit:cover;">
                                    </a>
                                    <?php if (($photo['captured_at'] ?? null) !== null): ?>
                                        <div class="text-muted" style="font-size:.7rem;"><?= $e(substr((string) $photo['captured_at'], 0, 16)) ?></div>
                                    <?php endif; ?>
                                    <?php if (($photo['lat'] ?? null) !== null && ($photo['lng'] ?? null) !== null): ?>
                                        <a class="small" style="font-size:.7rem;" target="_blank" rel="noopener"
                                           href="https://www.openstreetmap.org/?mlat=<?= $e((string) $photo['lat']) ?>&mlon=<?= $e((string) $photo['lng']) ?>">
                                            <i class="bi bi-geo-alt" aria-hidden="true"></i> GPS
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php if ($intervention['client_signature_path']): ?>
            <div class="card mb-3">
                <div class="card-header"><?= $e($t('worker.signature')) ?></div>
                <div class="card-body">
                    <img src="<?= $e(Url::to('/admin/interventions/' . $intervention['id'] . '/signature')) ?>" alt=""
                         class="border rounded" style="max-width:100%;max-height:200px;">
                </div>
            </div>
        <?php endif; ?>

        <div class="card mb-3">
            <div class="card-header"><?= $e($t('admin.interventions.history')) ?></div>
            <div class="card-body">
                <?php if ($history === []): ?>
                    <p class="text-muted small mb-0"><?= $e($t('admin.interventions.history_empty')) ?></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th><?= $e($t('admin.interventions.history_when')) ?></th>
                                    <th><?= $e($t('admin.interventions.history_from')) ?></th>
                                    <th><?= $e($t('admin.interventions.history_to')) ?></th>
                                    <th><?= $e($t('admin.interventions.history_by')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($history as $h): ?>
                                <tr>
                                    <td class="small"><?= $e($h['changed_at']) ?></td>
                                    <td><?php if ($h['from_status'] !== null): ?><?= View::render('partials/status_badge', ['group' => 'intervention_status', 'value' => (string) $h['from_status']], null) ?><?php else: ?>—<?php endif; ?></td>
                                    <td><?= View::render('partials/status_badge', ['group' => 'intervention_status', 'value' => (string) $h['to_status']], null) ?></td>
                                    <td><?= $e($h['changed_by_name']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="app-rail">
        <div class="app-rail-card">
            <h2 class="app-rail-title"><?= $e($t('admin.interventions.details')) ?></h2>

            <div class="mb-3">
                <?= View::render('partials/status_badge', ['group' => 'intervention_status', 'value' => $status], null) ?>
            </div>

            <div class="d-flex align-items-center gap-2 mb-3">
                <?php if (($intervention['worker_name'] ?? null) !== null): ?>
                    <span class="app-avatars">
                        <span class="app-avatar"><?= $e($initials((string) $intervention['worker_name'])) ?></span>
                    </span>
                    <span><?= $e($intervention['worker_name']) ?></span>
                <?php else: ?>
                    <span class="app-avatars">
                        <span class="app-avatar is-more"><i class="bi bi-person" aria-hidden="true"></i></span>
                    </span>
                    <span class="text-muted"><?= $e($t('admin.interventions.unassigned')) ?></span>
                <?php endif; ?>
            </div>

            <dl class="app-dl">
                <div class="app-dl-row">
                    <dt><?= $e($t('admin.interventions.scheduled_date')) ?></dt>
                    <dd>
                        <?= $e($intervention['scheduled_date'] ?? '—') ?>
                        <?= $intervention['scheduled_start_time'] ? ' ' . $e(substr((string) $intervention['scheduled_start_time'], 0, 5)) : '' ?>
                    </dd>
                </div>
                <?php if ($intervention['started_at']): ?>
                    <div class="app-dl-row">
                        <dt><?= $e($t('admin.interventions.started_at')) ?></dt>
                        <dd><?= $e($intervention['started_at']) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($intervention['completed_at']): ?>
                    <div class="app-dl-row">
                        <dt><?= $e($t('admin.interventions.completed_at')) ?></dt>
                        <dd><?= $e($intervention['completed_at']) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>

            <?php if ($intervention['completion_notes']): ?>
                <div class="mt-3">
                    <div class="small text-muted mb-1"><?= $e($t('worker.completion_notes')) ?></div>
                    <div class="small"><?= nl2br($e($intervention['completion_notes'])) ?></div>
                </div>
            <?php endif; ?>

            <?php if (($nextActions[$status] ?? []) !== []): ?>
                <div class="mt-3 pt-3 border-top d-grid gap-2">
                    <?php foreach ($nextActions[$status] as $action): ?>
                        <button type="button" class="btn btn-sm <?= $action['to'] === 'cancelled' ? 'btn-outline-danger' : 'btn-outline-success' ?> js-intervention-status"
                                data-url="<?= $e(Url::to('/admin/interventions/' . $intervention['id'] . '/status')) ?>"
                                data-to-status="<?= $e($action['to']) ?>"
                                <?= $action['to'] === 'cancelled' ? 'data-confirm="' . $e($t('admin.interventions.cancel_confirm')) . '"' : '' ?>>
                            <?= $e($action['label']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
