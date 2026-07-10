<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $notifications */
/** @var bool $unreadOnly */
/** @var int $unreadCount */

$e  = static fn (?string $v): string => View::e($v);
$t  = static fn (string $key): string => Lang::get($key);
$dt = static fn (?string $v): string => $v ? substr((string) $v, 0, 16) : '';

// Notification type -> icon + severity -> row stripe / text colour.
$icons = [
    'compliance_expiry'    => 'bi-shield-exclamation',
    'quote_expired'        => 'bi-file-earmark-text',
    'intervention_overdue' => 'bi-calendar-x',
    'low_stock'            => 'bi-box-seam',
    'system'               => 'bi-info-circle',
];
$rowClass = ['danger' => 'sev-bad', 'warning' => 'sev-warn', 'info' => ''];
$sevText  = ['danger' => 'text-danger', 'warning' => 'text-warning', 'info' => 'text-secondary'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('notifications.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('notifications.subtitle')) ?></p>
    </div>
    <?php if ($unreadCount > 0): ?>
        <button type="button" class="btn btn-sm btn-outline-secondary js-post-action"
                data-url="<?= $e(Url::to('/admin/notifications/read-all')) ?>">
            <i class="bi bi-check2-all me-1" aria-hidden="true"></i><?= $e($t('notifications.mark_all_read')) ?>
        </button>
    <?php endif; ?>
</div>

<div class="btn-group btn-group-sm mb-3" role="group">
    <a class="btn <?= $unreadOnly ? 'btn-outline-secondary' : 'btn-secondary' ?>"
       href="<?= $e(Url::to('/admin/notifications')) ?>"><?= $e($t('notifications.all')) ?></a>
    <a class="btn <?= $unreadOnly ? 'btn-secondary' : 'btn-outline-secondary' ?>"
       href="<?= $e(Url::to('/admin/notifications?filter=unread')) ?>">
        <?= $e($t('notifications.unread')) ?><?= $unreadCount > 0 ? ' (' . $e((string) $unreadCount) . ')' : '' ?>
    </a>
</div>

<?php if ($notifications === []): ?>
    <?= View::render('partials/empty_state', [
        'message' => $unreadOnly ? $t('notifications.none_unread') : $t('notifications.empty'),
        'hint'    => $t('notifications.subtitle'),
        'actions' => [],
    ], null) ?>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <tbody>
                <?php foreach ($notifications as $n):
                    $sev  = (string) $n['severity'];
                    $icon = $icons[(string) $n['type']] ?? 'bi-bell';
                    $link = $n['link'] !== null ? Url::to((string) $n['link']) : null;
                ?>
                    <tr class="<?= $e($rowClass[$sev] ?? '') ?><?= (int) $n['is_read'] === 0 ? ' fw-semibold' : ' text-muted' ?>">
                        <td style="width:2.2rem">
                            <i class="bi <?= $e($icon) ?> <?= $e($sevText[$sev] ?? '') ?>" aria-hidden="true"></i>
                        </td>
                        <td>
                            <?php if ($link !== null): ?>
                                <a class="text-decoration-none" href="<?= $e($link) ?>"><?= $e((string) $n['title']) ?></a>
                            <?php else: ?>
                                <?= $e((string) $n['title']) ?>
                            <?php endif; ?>
                            <?php if ($n['body'] !== null): ?>
                                <div class="small text-muted fw-normal"><?= $e((string) $n['body']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap small text-muted fw-normal"><?= $e($dt($n['created_at'])) ?></td>
                        <td class="text-end">
                            <?php if ((int) $n['is_read'] === 0): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary js-post-action"
                                        data-url="<?= $e(Url::to('/admin/notifications/' . $n['id'] . '/read')) ?>"
                                        title="<?= $e($t('notifications.mark_read')) ?>"
                                        aria-label="<?= $e($t('notifications.mark_read')) ?>">
                                    <i class="bi bi-check2" aria-hidden="true"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
