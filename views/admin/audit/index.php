<?php
use App\Support\Lang;
use App\Support\View;

/** @var array<int,array<string,mixed>> $entries */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $k): string => Lang::get($k);

$actionColor = [
    'created'     => 'app-status-success',
    'updated'     => 'app-status-info',
    'deleted'     => 'app-status-danger',
    'deactivated' => 'app-status-warning',
    'activated'   => 'app-status-success',
    'reset'       => 'app-status-neutral',
];
?>
<div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.audit.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.audit.subtitle')) ?></p>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= $e($t('admin.audit.when')) ?></th>
                    <th><?= $e($t('admin.audit.user')) ?></th>
                    <th><?= $e($t('admin.audit.action')) ?></th>
                    <th><?= $e($t('admin.audit.entity')) ?></th>
                    <th><?= $e($t('admin.audit.detail')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($entries === []): ?>
                <tr><td colspan="5" class="text-center text-muted py-4"><?= $e($t('admin.audit.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($entries as $a): ?>
                <tr>
                    <td class="small text-nowrap"><?= $e(date('d/m/Y H:i', (int) strtotime((string) $a['created_at']))) ?></td>
                    <td><?= $e($a['user_name'] ?? '—') ?></td>
                    <td><span class="app-status <?= $e($actionColor[$a['action']] ?? 'app-status-neutral') ?>"><?= $e(Lang::label('audit_actions', (string) $a['action'])) ?></span></td>
                    <td><?= $e(Lang::label('audit_entities', (string) $a['entity'])) ?><?= $a['entity_id'] ? ' <span class="text-muted small">#' . $e((string) $a['entity_id']) . '</span>' : '' ?></td>
                    <td class="small"><?= $e($a['summary'] ?? '') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isset($paginator)) { echo View::render('partials/pagination', ['paginator' => $paginator], null); } ?>
