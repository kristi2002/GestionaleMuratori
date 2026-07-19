<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $leads */
/** @var array<string,int> $counts */
/** @var string $status */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $k): string => Lang::get($k);

$badge = ['new' => 'warning', 'contacted' => 'info', 'converted' => 'success', 'archived' => 'secondary'];

echo View::render('partials/page_head', [
    'title'    => $t('admin.leads.title'),
    'subtitle' => $t('admin.leads.subtitle'),
    'actions'  => View::render('partials/back_button', ['href' => '/admin'], null),
], null);

echo View::render('partials/filter_pills', ['pills' => [
    ['label' => $t('common.all'), 'href' => '/admin/leads', 'active' => $status === '', 'count' => $counts['_total'] ?? 0],
    ['label' => Lang::label('lead_status', 'new'),       'href' => '/admin/leads?status=new',       'active' => $status === 'new',       'count' => $counts['new'] ?? 0,       'dot' => 'warning'],
    ['label' => Lang::label('lead_status', 'contacted'), 'href' => '/admin/leads?status=contacted', 'active' => $status === 'contacted', 'count' => $counts['contacted'] ?? 0, 'dot' => 'info'],
    ['label' => Lang::label('lead_status', 'converted'), 'href' => '/admin/leads?status=converted', 'active' => $status === 'converted', 'count' => $counts['converted'] ?? 0, 'dot' => 'success'],
    ['label' => Lang::label('lead_status', 'archived'),  'href' => '/admin/leads?status=archived',  'active' => $status === 'archived',  'count' => $counts['archived'] ?? 0,  'dot' => 'secondary'],
]], null);
?>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th><?= $e($t('admin.leads.name')) ?></th>
                    <th class="d-none d-md-table-cell"><?= $e($t('admin.leads.contact')) ?></th>
                    <th class="d-none d-md-table-cell"><?= $e($t('admin.leads.received')) ?></th>
                    <th><?= $e($t('admin.leads.status')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php if ($leads === []): ?>
                <tr><td colspan="4" class="text-center text-muted py-4"><?= $e($t('admin.leads.empty')) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($leads as $lead): ?>
                <tr>
                    <td>
                        <a href="<?= $e(Url::to('/admin/leads/' . $lead['id'])) ?>" class="app-card-title-link fw-semibold"><?= $e($lead['name']) ?></a>
                        <?php if (($lead['message'] ?? null) !== null && $lead['message'] !== ''): ?>
                            <div class="small text-muted text-truncate" style="max-width:32rem;"><?= $e($lead['message']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-md-table-cell small">
                        <?php if (($lead['email'] ?? null) !== null && $lead['email'] !== ''): ?>
                            <div><i class="bi bi-envelope text-muted" aria-hidden="true"></i> <?= $e($lead['email']) ?></div>
                        <?php endif; ?>
                        <?php if (($lead['phone'] ?? null) !== null && $lead['phone'] !== ''): ?>
                            <div><i class="bi bi-telephone text-muted" aria-hidden="true"></i> <?= $e($lead['phone']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-md-table-cell small text-muted"><?= $e(substr((string) $lead['created_at'], 0, 16)) ?></td>
                    <td>
                        <span class="badge bg-<?= $e($badge[$lead['status']] ?? 'secondary') ?>-subtle text-<?= $e($badge[$lead['status']] ?? 'secondary') ?>-emphasis">
                            <?= $e(Lang::label('lead_status', (string) $lead['status'])) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (isset($paginator) && $paginator->total > 0): ?>
    <?= View::render('partials/pagination', ['paginator' => $paginator], null) ?>
<?php endif; ?>
