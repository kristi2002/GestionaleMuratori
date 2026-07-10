<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed> $quote */
/** @var array<int,array<string,mixed>> $lines */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$euro = static fn ($v): string => '€ ' . number_format((float) $v, 2, ',', '.');
$qty = static fn ($v): string => rtrim(rtrim((string) $v, '0'), '.');

$subtotal = 0.0;
foreach ($lines as $l) {
    $subtotal += (float) $l['qty'] * (float) $l['unit_price'];
}
$vatRate = (float) $quote['vat_rate'];
$vatAmt  = $subtotal * $vatRate / 100;
$total   = $subtotal + $vatAmt;
$badge = ['sent' => 'text-bg-primary', 'accepted' => 'text-bg-success', 'rejected' => 'text-bg-secondary', 'expired' => 'text-bg-warning'];
?>
<div class="mb-3">
    <a class="btn btn-sm btn-link text-decoration-none px-0" href="<?= $e(Url::to('/client/quotes')) ?>">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= $e($t('client.quotes.back')) ?>
    </a>
</div>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1"><?= $e($quote['title']) ?></h1>
        <p class="text-muted mb-0">
            <?= $e($t('client.quotes.number')) ?> <?= $e($quote['number']) ?> · <?= $e($quote['quote_date']) ?>
            <?php if ($quote['valid_until']): ?>
                · <?= $e($t('client.quotes.valid_until')) ?> <?= $e($quote['valid_until']) ?>
            <?php endif; ?>
        </p>
    </div>
    <span class="badge <?= $e($badge[$quote['status']] ?? 'text-bg-light') ?> fs-6">
        <?= $e(Lang::label('quote_status', $quote['status'])) ?>
    </span>
</div>

<div class="card mb-3">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th><?= $e($t('client.quotes.description')) ?></th>
                    <th class="text-end"><?= $e($t('client.quotes.qty')) ?></th>
                    <th class="text-end"><?= $e($t('client.quotes.unit_price')) ?></th>
                    <th class="text-end"><?= $e($t('client.quotes.line_total')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($lines as $l): ?>
                <tr>
                    <td><?= $e($l['description']) ?></td>
                    <td class="text-end tnum"><?= $e($qty($l['qty'])) ?><?= $l['unit'] ? ' ' . $e($l['unit']) : '' ?></td>
                    <td class="text-end tnum"><?= $e($euro($l['unit_price'])) ?></td>
                    <td class="text-end tnum"><?= $e($euro((float) $l['qty'] * (float) $l['unit_price'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr><td colspan="3" class="text-end text-muted"><?= $e($t('client.quotes.subtotal')) ?></td><td class="text-end tnum"><?= $e($euro($subtotal)) ?></td></tr>
                <tr><td colspan="3" class="text-end text-muted"><?= $e($t('client.quotes.vat')) ?> (<?= $e($qty($vatRate)) ?>%)</td><td class="text-end tnum"><?= $e($euro($vatAmt)) ?></td></tr>
                <tr class="fw-bold"><td colspan="3" class="text-end"><?= $e($t('client.quotes.total')) ?></td><td class="text-end tnum"><?= $e($euro($total)) ?></td></tr>
            </tfoot>
        </table>
    </div>
</div>

<?php if ($quote['notes']): ?>
    <div class="card mb-3"><div class="card-body">
        <h2 class="h6 text-muted"><?= $e($t('client.quotes.notes')) ?></h2>
        <p class="mb-0" style="white-space:pre-line"><?= $e($quote['notes']) ?></p>
    </div></div>
<?php endif; ?>

<?php if ($quote['status'] === 'sent'): ?>
    <div class="card">
        <div class="card-body">
            <p class="mb-3"><?= $e($t('client.quotes.decision_prompt')) ?></p>
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-success js-post-action"
                        data-url="<?= $e(Url::to('/client/quotes/' . $quote['id'] . '/accept')) ?>"
                        data-confirm="<?= $e($t('client.quotes.confirm_accept')) ?>">
                    <i class="bi bi-check2-circle me-1" aria-hidden="true"></i><?= $e($t('client.quotes.accept')) ?>
                </button>
                <button type="button" class="btn btn-outline-danger js-post-action"
                        data-url="<?= $e(Url::to('/client/quotes/' . $quote['id'] . '/reject')) ?>"
                        data-confirm="<?= $e($t('client.quotes.confirm_reject')) ?>">
                    <i class="bi bi-x-circle me-1" aria-hidden="true"></i><?= $e($t('client.quotes.reject')) ?>
                </button>
            </div>
        </div>
    </div>
<?php elseif ($quote['status'] === 'accepted'): ?>
    <div class="alert alert-success"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i><?= $e($t('client.quotes.accepted_notice')) ?></div>
<?php elseif ($quote['status'] === 'rejected'): ?>
    <div class="alert alert-secondary"><?= $e($t('client.quotes.rejected_notice')) ?></div>
<?php elseif ($quote['status'] === 'expired'): ?>
    <div class="alert alert-warning"><?= $e($t('client.quotes.expired_notice')) ?></div>
<?php endif; ?>
