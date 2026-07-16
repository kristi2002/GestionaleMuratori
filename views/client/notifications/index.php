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

$sevText = ['danger' => 'text-danger', 'warning' => 'text-warning', 'info' => 'text-secondary'];

$actions = $unreadCount > 0
    ? '<button type="button" class="btn btn-outline-secondary js-post-action" data-url="'
        . $e(Url::to('/client/notifications/read-all')) . '">'
        . '<i class="bi bi-check2-all" aria-hidden="true"></i> ' . $e($t('notifications.mark_all_read')) . '</button>'
    : null;

echo View::render('partials/page_head', [
    'title'    => $t('notifications.title'),
    'subtitle' => $t('notifications.subtitle'),
    'actions'  => $actions,
], null);

echo View::render('partials/filter_pills', ['pills' => [
    [
        'label'  => $t('notifications.all'),
        'href'   => '/client/notifications',
        'active' => !$unreadOnly,
    ],
    [
        'label'  => $t('notifications.unread'),
        'href'   => '/client/notifications?filter=unread',
        'active' => $unreadOnly,
        'count'  => $unreadCount > 0 ? $unreadCount : null,
        'dot'    => 'warning',
    ],
]], null);
?>

<?php if ($notifications === []): ?>
    <?= View::render('partials/empty_state', [
        'message' => $unreadOnly ? $t('notifications.none_unread') : $t('notifications.empty'),
        'hint'    => $t('notifications.subtitle'),
        'actions' => [],
    ], null) ?>
<?php else: ?>
    <div class="card">
        <div class="list-group list-group-flush">
            <?php foreach ($notifications as $n):
                $sev    = (string) $n['severity'];
                $link   = $n['link'] !== null ? Url::to((string) $n['link']) : null;
                $unread = (int) $n['is_read'] === 0;
            ?>
                <div class="list-group-item d-flex align-items-start gap-3<?= $unread ? '' : ' text-muted' ?>">
                    <span class="app-timeline-icon <?= $sev === 'info' ? 'is-project' : '' ?>">
                        <i class="bi bi-bell <?= $unread ? $e($sevText[$sev] ?? '') : '' ?>" aria-hidden="true"></i>
                    </span>
                    <div class="flex-grow-1 min-w-0">
                        <div class="d-flex align-items-start justify-content-between gap-2">
                            <div class="<?= $unread ? 'fw-semibold' : 'text-muted' ?>">
                                <?php if ($link !== null): ?>
                                    <a class="app-card-title-link" href="<?= $e($link) ?>"><?= $e((string) $n['title']) ?></a>
                                <?php else: ?>
                                    <?= $e((string) $n['title']) ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($unread): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary app-icon-btn js-post-action flex-shrink-0"
                                        data-url="<?= $e(Url::to('/client/notifications/' . $n['id'] . '/read')) ?>"
                                        title="<?= $e($t('notifications.mark_read')) ?>"
                                        aria-label="<?= $e($t('notifications.mark_read')) ?>">
                                    <i class="bi bi-check2" aria-hidden="true"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                        <?php if ($n['body'] !== null): ?>
                            <div class="small text-muted"><?= $e((string) $n['body']) ?></div>
                        <?php endif; ?>
                        <div class="small text-muted mt-1">
                            <i class="bi bi-clock" aria-hidden="true"></i> <?= $e($dt($n['created_at'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
