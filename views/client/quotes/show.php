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

$subParts = [$t('client.quotes.number') . ' ' . $quote['number'], (string) $quote['quote_date']];
if ($quote['valid_until']) {
    $subParts[] = $t('client.quotes.valid_until') . ' ' . $quote['valid_until'];
}

$actions = '<a class="btn btn-outline-secondary" href="' . $e(Url::to('/client/quotes')) . '">'
    . '<i class="bi bi-arrow-left" aria-hidden="true"></i> ' . $e($t('client.quotes.back')) . '</a>';

echo View::render('partials/page_head', [
    'title'    => (string) $quote['title'],
    'subtitle' => implode(' · ', $subParts),
    'actions'  => $actions,
], null);
?>

<div class="app-cols">
    <div>
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
            <div class="card mb-3">
                <div class="card-body">
                    <h2 class="app-rail-title"><?= $e($t('client.quotes.notes')) ?></h2>
                    <p class="mb-0" style="white-space:pre-line"><?= $e($quote['notes']) ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="app-rail">
        <div class="app-rail-card">
            <h2 class="app-rail-title"><?= $e($t('client.quotes.status')) ?></h2>

            <div class="mb-3">
                <?= View::render('partials/status_badge', ['group' => 'quote_status', 'value' => (string) $quote['status']], null) ?>
            </div>

            <dl class="app-dl">
                <div class="app-dl-row">
                    <dt><?= $e($t('client.quotes.subtotal')) ?></dt>
                    <dd class="tnum"><?= $e($euro($subtotal)) ?></dd>
                </div>
                <div class="app-dl-row">
                    <dt><?= $e($t('client.quotes.vat')) ?> (<?= $e($qty($vatRate)) ?>%)</dt>
                    <dd class="tnum"><?= $e($euro($vatAmt)) ?></dd>
                </div>
                <div class="app-dl-row">
                    <dt class="fw-bold"><?= $e($t('client.quotes.total')) ?></dt>
                    <dd class="tnum fw-bold"><?= $e($euro($total)) ?></dd>
                </div>
            </dl>

            <?php if ($quote['status'] === 'sent'): ?>
                <div class="mt-3 pt-3 border-top">
                    <p class="small mb-3"><?= $e($t('client.quotes.decision_prompt')) ?></p>
                    <div class="d-grid gap-2">
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
            <?php elseif ($quote['status'] === 'accepted'): ?>
                <div class="app-banner is-soft mt-3"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i><?= $e($t('client.quotes.accepted_notice')) ?></div>
            <?php elseif ($quote['status'] === 'rejected'): ?>
                <div class="app-banner is-soft mt-3"><?= $e($t('client.quotes.rejected_notice')) ?></div>
            <?php elseif ($quote['status'] === 'expired'): ?>
                <div class="app-banner is-warn mt-3"><?= $e($t('client.quotes.expired_notice')) ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>
