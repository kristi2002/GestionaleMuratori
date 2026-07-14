<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $projects */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

echo View::render('partials/page_head', [
    'title'    => $t('sub.projects_title'),
    'subtitle' => $t('sub.projects_subtitle'),
], null);
?>

<?php if ($projects === []): ?>
    <?= View::render('partials/empty_state', [
        'message' => $t('sub.empty_projects'),
        'actions' => [],
    ], null) ?>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($projects as $p): ?>
            <?php $mediaTint = ((int) $p['id'] % 2 === 0) ? ' tint-2' : ''; ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100 app-record-card">
                    <div class="card-body d-flex flex-column">
                        <a href="<?= $e(Url::to('/sub/projects/' . $p['id'])) ?>" class="app-card-media<?= $mediaTint ?> mb-3 text-decoration-none">
                            <i class="bi bi-building app-card-media-glyph" aria-hidden="true"></i>
                            <?= View::render('partials/status_badge', ['group' => 'project_status', 'value' => (string) $p['status']], null) ?>
                        </a>
                        <h2 class="h6 mb-1 text-truncate">
                            <a class="app-card-title-link" href="<?= $e(Url::to('/sub/projects/' . $p['id'])) ?>">
                                <?= $e($p['name']) ?>
                            </a>
                        </h2>
                        <p class="small text-muted mb-2 text-truncate">
                            <i class="bi bi-person" aria-hidden="true"></i>
                            <?= $e($p['client_name']) ?>
                        </p>
                        <ul class="list-unstyled small mb-3 app-card-meta">
                            <li>
                                <i class="bi bi-geo-alt" aria-hidden="true"></i>
                                <span class="text-truncate"><?= $e(($p['location'] ?? '') !== '' ? $p['location'] : '—') ?></span>
                            </li>
                            <li>
                                <i class="bi bi-calendar-event" aria-hidden="true"></i>
                                <span>
                                    <?= $e($p['start_date']) ?><?= ($p['end_date'] ?? '') !== '' ? ' → ' . $e($p['end_date']) : '' ?>
                                </span>
                            </li>
                        </ul>
                        <div class="mt-auto pt-3 border-top">
                            <a class="btn btn-sm btn-success" href="<?= $e(Url::to('/sub/projects/' . $p['id'])) ?>">
                                <i class="bi bi-folder2-open" aria-hidden="true"></i> <?= $e($t('common.open')) ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
