<?php
use App\Support\Lang;
use App\Support\View;

/** @var array<string,mixed> $invoice Row from ProjectInvoiceModel::findWithDetails(). */
/** @var array<int,array<string,mixed>> $lines Fiscal line items (empty for a legacy invoice). */
/** @var string $generated_at */

$lines = $lines ?? [];
$e     = static fn (?string $v): string => View::e($v);
$t     = static fn (string $key): string => Lang::get($key);
$money = static fn ($v): string => $v === null ? '—' : '€ ' . number_format((float) $v, 2, ',', '.');
$date  = static fn (?string $v): string => $v ? date('d/m/Y', (int) strtotime($v)) : '—';
$num   = static fn ($v): string => rtrim(rtrim((string) $v, '0'), '.');

// A fiscal invoice recomputes its VAT summary from the stored lines.
$totals = $lines !== []
    ? \App\Support\InvoiceTotals::compute($lines, [
        'ritenuta_rate' => $invoice['ritenuta_rate'] ?? 0,
        'bollo'         => $invoice['bollo'] ?? 0,
    ])
    : null;
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
    .status-badge { font-size: 8.5pt; padding: 1pt 5pt; border: 1px solid #999; border-radius: 8pt; }
    .total-box { margin-top: 10pt; text-align: right; font-size: 13pt; font-weight: bold; }
</style>
</head>
<body>
    <?= View::render('reports/partials/pdf_header', [
        'doc_title'    => sprintf($t('report.invoice_number'), (string) $invoice['number']),
        'doc_subtitle' => $t('report.invoice_subtitle'),
    ], null) ?>

    <h2><?= $e($t('report.client')) ?></h2>
    <table class="data">
        <tr><th width="25%"><?= $e($t('report.name')) ?></th><td><?= $e($invoice['client_name']) ?></td></tr>
        <?php if ($invoice['client_vat']): ?>
            <tr><th><?= $e($t('report.vat_cf')) ?></th><td><?= $e($invoice['client_vat']) ?></td></tr>
        <?php endif; ?>
        <?php if ($invoice['client_address']): ?>
            <tr><th><?= $e($t('report.address')) ?></th><td><?= $e($invoice['client_address']) ?></td></tr>
        <?php endif; ?>
        <?php if ($invoice['client_email']): ?>
            <tr><th><?= $e($t('report.email')) ?></th><td><?= $e($invoice['client_email']) ?></td></tr>
        <?php endif; ?>
    </table>

    <h2><?= $e($t('report.invoice_details')) ?></h2>
    <table class="data">
        <tr><th width="25%"><?= $e($t('report.number')) ?></th><td><?= $e($invoice['number']) ?></td></tr>
        <tr><th><?= $e($t('report.issue_date')) ?></th><td><?= $e($date($invoice['issue_date'])) ?></td></tr>
        <tr><th><?= $e($t('report.project')) ?></th>
            <td>
                <?= $e($invoice['project_name']) ?>
                <?= $invoice['project_location'] ? ' — ' . $e($invoice['project_location']) : '' ?>
            </td>
        </tr>
        <tr><th><?= $e($t('report.status')) ?></th><td><span class="status-badge"><?= $e(Lang::label('invoice_status', $invoice['status'])) ?></span></td></tr>
        <?php if ($invoice['note']): ?>
            <tr><th><?= $e($t('report.note')) ?></th><td><?= $e($invoice['note']) ?></td></tr>
        <?php endif; ?>
        <tr><th><?= $e($t('report.registered_by')) ?></th><td><?= $e($invoice['created_by_name']) ?></td></tr>
    </table>

    <?php if ($totals !== null): ?>
        <h2><?= $e($t('admin.invoices.section_lines')) ?></h2>
        <table class="data">
            <thead>
                <tr>
                    <th><?= $e($t('admin.quotes.line_description')) ?></th>
                    <th width="10%"><?= $e($t('admin.quotes.line_qty')) ?></th>
                    <th width="15%"><?= $e($t('admin.quotes.line_price')) ?></th>
                    <th width="8%"><?= $e($t('admin.invoices.line_vat')) ?></th>
                    <th width="10%"><?= $e($t('admin.invoices.line_natura')) ?></th>
                    <th width="15%" style="text-align:right;"><?= $e($t('admin.quotes.line_total')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $l): ?>
                    <tr>
                        <td><?= $e($l['description']) ?></td>
                        <td><?= $e($num($l['qty'])) ?> <?= $e((string) ($l['unit'] ?? '')) ?></td>
                        <td><?= $e($money($l['unit_price'])) ?></td>
                        <td><?= $e($num($l['vat_rate'])) ?>%</td>
                        <td><?= $e((string) ($l['natura'] ?? '')) ?></td>
                        <td style="text-align:right;"><?= $e($money($l['line_total'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2><?= $e($t('report.vat_summary')) ?></h2>
        <table class="data">
            <thead>
                <tr>
                    <th><?= $e($t('admin.invoices.line_vat')) ?></th>
                    <th><?= $e($t('admin.invoices.line_natura')) ?></th>
                    <th style="text-align:right;"><?= $e($t('report.subtotal')) ?></th>
                    <th style="text-align:right;"><?= $e($t('report.vat')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($totals['riepilogo'] as $r): ?>
                    <tr>
                        <td><?= $e($num($r['vat_rate'])) ?>%</td>
                        <td><?= $e((string) ($r['natura'] ?? '')) ?><?= $r['natura'] !== null ? ' — ' . $e($t('report.reverse_charge')) : '' ?></td>
                        <td style="text-align:right;"><?= $e($money($r['imponibile'])) ?></td>
                        <td style="text-align:right;"><?= $e($money($r['imposta'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <table style="width:100%; margin-top:10pt;">
            <tr><td style="text-align:right; padding:1pt 0;"><?= $e($t('report.subtotal')) ?>:</td><td width="120" style="text-align:right;"><?= $e($money($totals['imponibile'])) ?></td></tr>
            <tr><td style="text-align:right; padding:1pt 0;"><?= $e($t('report.vat')) ?>:</td><td style="text-align:right;"><?= $e($money($totals['imposta'])) ?></td></tr>
            <?php if ($totals['bollo'] > 0): ?>
                <tr><td style="text-align:right; padding:1pt 0;"><?= $e($t('admin.invoices.bollo')) ?>:</td><td style="text-align:right;"><?= $e($money($totals['bollo'])) ?></td></tr>
            <?php endif; ?>
            <tr><td style="text-align:right; padding:2pt 0; font-weight:bold;"><?= $e($t('admin.invoices.total_document')) ?>:</td><td style="text-align:right; font-weight:bold;"><?= $e($money($totals['total_document'])) ?></td></tr>
            <?php if ($totals['ritenuta'] > 0): ?>
                <tr><td style="text-align:right; padding:1pt 0;"><?= $e($t('admin.invoices.ritenuta')) ?>:</td><td style="text-align:right;">− <?= $e($money($totals['ritenuta'])) ?></td></tr>
                <tr><td style="text-align:right; padding:2pt 0; font-weight:bold;"><?= $e($t('admin.invoices.net_to_pay')) ?>:</td><td style="text-align:right; font-weight:bold;"><?= $e($money($totals['net_to_pay'])) ?></td></tr>
            <?php endif; ?>
        </table>
        <?php if ((int) ($invoice['split_payment'] ?? 0) === 1): ?>
            <p class="muted"><?= $e($t('admin.invoices.split_payment')) ?></p>
        <?php endif; ?>
    <?php else: ?>
        <div class="total-box"><?= $e($t('report.amount')) ?>: <?= $e($money($invoice['amount'])) ?></div>
    <?php endif; ?>

    <p class="muted" style="margin-top:12pt;"><?= $e(sprintf($t('report.generated_on'), $generated_at)) ?></p>
</body>
</html>
