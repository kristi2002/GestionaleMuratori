<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $quotes */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$euro = static fn ($v): string => '€ ' . number_format((float) $v, 2, ',', '.');

echo View::render('partials/page_head', [
    'title'    => $t('client.quotes.title'),
    'subtitle' => $t('client.quotes.subtitle'),
], null);
?>

<?php if ($quotes === []): ?>
    <div class="card">
        <div class="app-empty-state">
            <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
            <p class="mb-0 fw-semibold"><?= $e($t('client.quotes.empty')) ?></p>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= $e($t('client.quotes.number')) ?></th>
                        <th><?= $e($t('client.quotes.field_title')) ?></th>
                        <th><?= $e($t('client.quotes.date')) ?></th>
                        <th class="text-end"><?= $e($t('client.quotes.total')) ?></th>
                        <th><?= $e($t('client.quotes.status')) ?></th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($quotes as $q):
                    $total = (float) $q['subtotal'] * (1 + (float) $q['vat_rate'] / 100);
                ?>
                    <tr>
                        <td class="fw-semibold"><?= $e($q['number']) ?></td>
                        <td>
                            <a class="app-card-title-link" href="<?= $e(Url::to('/client/quotes/' . $q['id'])) ?>"><?= $e($q['title']) ?></a>
                            <?php if ($q['project_name']): ?>
                                <div class="small text-muted"><?= $e($q['project_name']) ?></div>
                            <?php endif; ?>
                            <?php if ($q['status'] === 'sent'): ?>
                                <div class="small text-warning">
                                    <i class="bi bi-hourglass-split" aria-hidden="true"></i> <?= $e($t('client.quotes.awaiting_you')) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= $e($q['quote_date']) ?></td>
                        <td class="text-end tnum"><?= $e($euro($total)) ?></td>
                        <td>
                            <?= View::render('partials/status_badge', ['group' => 'quote_status', 'value' => (string) $q['status']], null) ?>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-success" href="<?= $e(Url::to('/client/quotes/' . $q['id'])) ?>">
                                <i class="bi bi-folder2-open" aria-hidden="true"></i> <?= $e($t('common.open')) ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
