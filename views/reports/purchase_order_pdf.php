<?php
use App\Support\Lang;
use App\Support\View;

/** @var array<string,mixed> $order Row from PurchaseOrderModel::find(). */
/** @var array<int,array<string,mixed>> $lines */
/** @var string $generated_at */

$e     = static fn (?string $v): string => View::e($v);
$t     = static fn (string $key): string => Lang::get($key);
$money = static fn ($v): string => '€ ' . number_format((float) $v, 2, ',', '.');
$date  = static fn (?string $v): string => $v ? date('d/m/Y', (int) strtotime($v)) : '—';
$qty   = static fn ($v): string => rtrim(rtrim((string) $v, '0'), '.');

$subtotal = 0.0;
foreach ($lines as $line) {
    $subtotal += (float) $line['qty'] * (float) $line['unit_price'];
}
$vatAmount = $subtotal * (float) $order['vat_rate'] / 100;
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<style>
    body { font-family: sans-serif; font-size: 11pt; color: #222; }
    h1 { font-size: 16pt; margin: 0 0 2pt; }
    h2 { font-size: 12pt; margin: 14pt 0 4pt; border-bottom: 1px solid #999; padding-bottom: 2pt; }
    .muted { color: #666; font-size: 9pt; }
    .header-table td { vertical-align: top; padding: 1pt 0; }
    table.data { width: 100%; border-collapse: collapse; margin-top: 4pt; }
    table.data th, table.data td { border: 1px solid #ccc; padding: 4pt 6pt; font-size: 9.5pt; text-align: left; }
    table.data th { background: #f0f0f0; }
    table.data td.num, table.data th.num { text-align: right; }
    .status-badge { font-size: 8.5pt; padding: 1pt 5pt; border: 1px solid #999; border-radius: 8pt; }
    table.totals { border-collapse: collapse; margin-top: 8pt; margin-left: auto; }
    table.totals td { padding: 2pt 6pt; font-size: 10pt; text-align: right; }
    table.totals tr.grand td { font-size: 13pt; font-weight: bold; border-top: 1px solid #999; }
</style>
</head>
<body>
    <?= View::render('reports/partials/pdf_header', [
        'doc_title'    => sprintf($t('report.order_number'), (string) $order['number']),
        'doc_subtitle' => $order['title'],
    ], null) ?>

    <h2><?= $e($t('report.supplier')) ?></h2>
    <table class="data">
        <tr><th width="25%"><?= $e($t('report.name')) ?></th><td><?= $e($order['supplier_name']) ?></td></tr>
        <?php if ($order['supplier_vat']): ?>
            <tr><th><?= $e($t('report.vat_cf')) ?></th><td><?= $e($order['supplier_vat']) ?></td></tr>
        <?php endif; ?>
        <?php if ($order['supplier_address']): ?>
            <tr><th><?= $e($t('report.address')) ?></th><td><?= $e($order['supplier_address']) ?></td></tr>
        <?php endif; ?>
        <?php if ($order['supplier_email']): ?>
            <tr><th><?= $e($t('report.email')) ?></th><td><?= $e($order['supplier_email']) ?></td></tr>
        <?php endif; ?>
    </table>

    <h2><?= $e($t('report.order_details')) ?></h2>
    <table class="data">
        <tr><th width="25%"><?= $e($t('report.date')) ?></th><td><?= $e($date($order['order_date'])) ?></td></tr>
        <?php if ($order['expected_date']): ?>
            <tr><th><?= $e($t('report.expected_date')) ?></th><td><?= $e($date($order['expected_date'])) ?></td></tr>
        <?php endif; ?>
        <tr><th><?= $e($t('report.delivery_location')) ?></th><td><?= $e($order['location_name']) ?></td></tr>
        <?php if ($order['project_name']): ?>
            <tr><th><?= $e($t('report.project')) ?></th><td><?= $e($order['project_name']) ?></td></tr>
        <?php endif; ?>
        <tr><th><?= $e($t('report.status')) ?></th><td><span class="status-badge"><?= $e(Lang::label('po_status', $order['status'])) ?></span></td></tr>
    </table>

    <h2><?= $e($t('report.items')) ?></h2>
    <table class="data">
        <thead>
            <tr>
                <th><?= $e($t('report.description')) ?></th>
                <th class="num" width="10%"><?= $e($t('report.qty')) ?></th>
                <th width="10%"><?= $e($t('report.unit')) ?></th>
                <th class="num" width="15%"><?= $e($t('report.unit_price')) ?></th>
                <th class="num" width="15%"><?= $e($t('report.line_total')) ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lines as $line): ?>
            <tr>
                <td><?= $e($line['description']) ?></td>
                <td class="num"><?= $e($qty($line['qty'])) ?></td>
                <td><?= $e($line['unit'] ?? '—') ?></td>
                <td class="num"><?= $e($money($line['unit_price'])) ?></td>
                <td class="num"><?= $e($money((float) $line['qty'] * (float) $line['unit_price'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <table class="totals">
        <tr><td><?= $e($t('report.subtotal')) ?>:</td><td><?= $e($money($subtotal)) ?></td></tr>
        <tr><td><?= $e($t('report.vat')) ?> (<?= $e($qty($order['vat_rate'])) ?>%):</td><td><?= $e($money($vatAmount)) ?></td></tr>
        <tr class="grand"><td><?= $e($t('report.total')) ?>:</td><td><?= $e($money($subtotal + $vatAmount)) ?></td></tr>
    </table>

    <?php if ($order['notes']): ?>
        <h2><?= $e($t('report.notes_conditions')) ?></h2>
        <p><?= nl2br($e($order['notes'])) ?></p>
    <?php endif; ?>

    <p class="muted" style="margin-top:12pt;"><?= $e(sprintf($t('report.generated_on'), $generated_at)) ?></p>
</body>
</html>
