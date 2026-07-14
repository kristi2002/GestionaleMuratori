<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $projects */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

// Real KPI aggregates derived from the client's own project list (no invented data).
$total    = count($projects);
$byStatus = ['active' => 0, 'on_hold' => 0, 'closed' => 0];
foreach ($projects as $p) {
    $s = (string) $p['status'];
    if (isset($byStatus[$s])) {
        $byStatus[$s]++;
    }
}

echo View::render('partials/page_head', [
    'title'    => $t('client.projects_title'),
    'subtitle' => $t('client.projects_subtitle'),
], null);
?>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="card gm-kpi h-100 is-primary">
            <div class="card-body">
                <i class="bi bi-building gm-kpi-ic" aria-hidden="true"></i>
                <div class="gm-kpi-val mt-2"><?= $e((string) $total) ?></div>
                <div class="gm-kpi-lab"><?= $e($t('client.kpi_total')) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card gm-kpi h-100 ok">
            <div class="card-body">
                <i class="bi bi-hammer gm-kpi-ic" aria-hidden="true"></i>
                <div class="gm-kpi-val mt-2"><?= $e((string) $byStatus['active']) ?></div>
                <div class="gm-kpi-lab"><?= $e(Lang::label('project_status', 'active')) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card gm-kpi h-100 warn">
            <div class="card-body">
                <i class="bi bi-pause-circle gm-kpi-ic" aria-hidden="true"></i>
                <div class="gm-kpi-val mt-2"><?= $e((string) $byStatus['on_hold']) ?></div>
                <div class="gm-kpi-lab"><?= $e(Lang::label('project_status', 'on_hold')) ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card gm-kpi h-100">
            <div class="card-body">
                <i class="bi bi-check2-circle gm-kpi-ic" aria-hidden="true"></i>
                <div class="gm-kpi-val mt-2"><?= $e((string) $byStatus['closed']) ?></div>
                <div class="gm-kpi-lab"><?= $e(Lang::label('project_status', 'closed')) ?></div>
            </div>
        </div>
    </div>
</div>

<?php if ($projects === []): ?>
    <div class="card">
        <div class="app-empty-state">
            <i class="bi bi-building" aria-hidden="true"></i>
            <p class="mb-0 fw-semibold"><?= $e($t('client.empty_projects')) ?></p>
        </div>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($projects as $p): ?>
            <?php $mediaTint = ((int) $p['id'] % 2 === 0) ? ' tint-2' : ''; ?>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card h-100 app-record-card">
                    <div class="card-body d-flex flex-column">
                        <a href="<?= $e(Url::to('/client/projects/' . $p['id'])) ?>" class="app-card-media<?= $mediaTint ?> mb-3 text-decoration-none">
                            <i class="bi bi-building app-card-media-glyph" aria-hidden="true"></i>
                            <?= View::render('partials/status_badge', ['group' => 'project_status', 'value' => (string) $p['status']], null) ?>
                        </a>
                        <h2 class="h6 mb-1 text-truncate">
                            <a class="app-card-title-link" href="<?= $e(Url::to('/client/projects/' . $p['id'])) ?>">
                                <?= $e($p['name']) ?>
                            </a>
                        </h2>
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
                        <div class="d-flex align-items-center gap-2 mt-auto pt-3 border-top">
                            <a class="btn btn-sm btn-success" href="<?= $e(Url::to('/client/projects/' . $p['id'])) ?>">
                                <i class="bi bi-folder2-open" aria-hidden="true"></i> <?= $e($t('common.open')) ?>
                            </a>
                            <a class="btn btn-sm btn-outline-secondary app-icon-btn ms-auto" href="<?= $e(Url::to('/client/projects/' . $p['id'] . '/report/pdf')) ?>"
                               title="<?= $e($t('report.pdf')) ?>" aria-label="<?= $e($t('report.pdf')) ?>">
                                <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>
                            </a>
                            <a class="btn btn-sm btn-outline-secondary app-icon-btn" href="<?= $e(Url::to('/client/projects/' . $p['id'] . '/report/excel')) ?>"
                               title="<?= $e($t('report.excel')) ?>" aria-label="<?= $e($t('report.excel')) ?>">
                                <i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
