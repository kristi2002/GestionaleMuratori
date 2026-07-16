<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed> $project */
/** @var array<int,array<string,mixed>> $interventions */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$actions = '<a class="btn btn-outline-secondary app-icon-btn" href="' . $e(Url::to('/client/projects/' . $project['id'] . '/report/pdf')) . '"'
    . ' title="' . $e($t('report.pdf')) . '" aria-label="' . $e($t('report.pdf')) . '">'
    . '<i class="bi bi-file-earmark-pdf" aria-hidden="true"></i></a>'
    . '<a class="btn btn-outline-secondary app-icon-btn" href="' . $e(Url::to('/client/projects/' . $project['id'] . '/report/excel')) . '"'
    . ' title="' . $e($t('report.excel')) . '" aria-label="' . $e($t('report.excel')) . '">'
    . '<i class="bi bi-file-earmark-spreadsheet" aria-hidden="true"></i></a>'
    . '<a class="btn btn-outline-secondary" href="' . $e(Url::to('/client')) . '">'
    . '<i class="bi bi-arrow-left" aria-hidden="true"></i> ' . $e($t('client.back_to_list')) . '</a>';

echo View::render('partials/page_head', [
    'title'    => (string) $project['name'],
    'subtitle' => ($project['location'] ?? '') !== '' ? (string) $project['location'] : null,
    'actions'  => $actions,
], null);
?>

<div class="app-cols">
    <div>
        <h2 class="app-section-title"><?= $e($t('client.interventions')) ?></h2>

        <?php if ($interventions === []): ?>
            <div class="card">
                <div class="app-empty-state">
                    <i class="bi bi-clipboard-check" aria-hidden="true"></i>
                    <p class="mb-0 fw-semibold"><?= $e($t('client.no_interventions')) ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="d-flex flex-column gap-3">
                <?php foreach ($interventions as $iv): ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <h3 class="h6 mb-1"><?= $e($iv['title']) ?></h3>
                                <?= View::render('partials/status_badge', ['group' => 'intervention_status', 'value' => (string) $iv['status']], null) ?>
                            </div>
                            <p class="small text-muted mb-2">
                                <?php if ($iv['scheduled_date']): ?>
                                    <i class="bi bi-calendar-event" aria-hidden="true"></i>
                                    <?= $e($iv['scheduled_date']) ?> ·
                                <?php endif; ?>
                                <i class="bi bi-person" aria-hidden="true"></i>
                                <?= $e($iv['worker_name'] ?? $t('client.unassigned')) ?>
                            </p>

                            <?php if ($iv['gallery'] === []): ?>
                                <p class="small text-muted mb-0"><?= $e($t('client.no_photos')) ?></p>
                            <?php else: ?>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($iv['gallery'] as $photo): ?>
                                        <a href="<?= $e(Url::to('/client/photos/' . $photo['id'])) ?>" target="_blank" rel="noopener">
                                            <img src="<?= $e(Url::to('/client/photos/' . $photo['id'] . '/thumb')) ?>" alt="<?= $e(Lang::label('photo_types', $photo['type'])) ?>"
                                                 class="rounded border" style="width:88px;height:88px;object-fit:cover;">
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h2 class="app-section-title mt-4"><?= $e($t('client.invoices')) ?></h2>
        <?php if ($invoices === []): ?>
            <div class="card"><div class="card-body"><p class="mb-0 text-muted"><?= $e($t('client.no_invoices')) ?></p></div></div>
        <?php else: ?>
            <div class="card">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th><?= $e($t('client.invoice_number')) ?></th>
                                <th><?= $e($t('client.invoice_date')) ?></th>
                                <th class="text-end"><?= $e($t('client.invoice_amount')) ?></th>
                                <th><?= $e($t('client.invoice_status')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $inv): ?>
                                <tr>
                                    <td class="fw-semibold"><?= $e((string) $inv['number']) ?></td>
                                    <td><?= $e((string) $inv['issue_date']) ?></td>
                                    <td class="text-end mono tnum"><?= $inv['amount'] !== null ? '€ ' . $e(number_format((float) $inv['amount'], 2, ',', '.')) : '—' ?></td>
                                    <td><?= View::render('partials/status_badge', ['group' => 'invoice_status', 'value' => (string) $inv['status']], null) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="app-rail">
        <div class="app-rail-card">
            <h2 class="app-rail-title"><?= $e($t('client.details')) ?></h2>

            <div class="mb-3">
                <?= View::render('partials/status_badge', ['group' => 'project_status', 'value' => (string) $project['status']], null) ?>
            </div>

            <dl class="app-dl">
                <?php if (($project['location'] ?? '') !== ''): ?>
                    <div class="app-dl-row">
                        <dt><?= $e($t('client.location')) ?></dt>
                        <dd><?= $e($project['location']) ?></dd>
                    </div>
                <?php endif; ?>
                <div class="app-dl-row">
                    <dt><?= $e($t('client.start_date')) ?></dt>
                    <dd><?= $e($project['start_date']) ?></dd>
                </div>
                <?php if ($project['end_date']): ?>
                    <div class="app-dl-row">
                        <dt><?= $e($t('client.end_date')) ?></dt>
                        <dd><?= $e($project['end_date']) ?></dd>
                    </div>
                <?php endif; ?>
            </dl>
        </div>
    </div>
</div>
