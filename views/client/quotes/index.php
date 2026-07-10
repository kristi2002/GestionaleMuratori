<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<int,array<string,mixed>> $quotes */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$euro = static fn ($v): string => '€ ' . number_format((float) $v, 2, ',', '.');
// status -> bootstrap contextual badge
$badge = ['sent' => 'text-bg-primary', 'accepted' => 'text-bg-success', 'rejected' => 'text-bg-secondary', 'expired' => 'text-bg-warning'];
?>
<h1 class="h4 mb-1"><?= $e($t('client.quotes.title')) ?></h1>
<p class="text-muted mb-3"><?= $e($t('client.quotes.subtitle')) ?></p>

<?php if ($quotes === []): ?>
    <div class="card">
        <div class="card-body text-center text-muted py-5"><?= $e($t('client.quotes.empty')) ?></div>
    </div>
<?php else: ?>
    <div class="d-flex flex-column gap-2">
        <?php foreach ($quotes as $q):
            $total = (float) $q['subtotal'] * (1 + (float) $q['vat_rate'] / 100);
        ?>
            <a class="card text-decoration-none text-reset" href="<?= $e(Url::to('/client/quotes/' . $q['id'])) ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <h2 class="h6 mb-1"><?= $e($q['title']) ?></h2>
                            <p class="small text-muted mb-0">
                                <?= $e($t('client.quotes.number')) ?> <?= $e($q['number']) ?> ·
                                <?= $e($q['quote_date']) ?>
                                <?php if ($q['project_name']): ?> · <?= $e($q['project_name']) ?><?php endif; ?>
                            </p>
                        </div>
                        <div class="text-end">
                            <span class="badge <?= $e($badge[$q['status']] ?? 'text-bg-light') ?>">
                                <?= $e(Lang::label('quote_status', $q['status'])) ?>
                            </span>
                            <div class="fw-bold tnum mt-1"><?= $e($euro($total)) ?></div>
                        </div>
                    </div>
                    <?php if ($q['status'] === 'sent'): ?>
                        <p class="small text-primary mb-0 mt-2">
                            <i class="bi bi-hourglass-split me-1" aria-hidden="true"></i><?= $e($t('client.quotes.awaiting_you')) ?>
                        </p>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
