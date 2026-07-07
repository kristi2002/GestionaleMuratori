<?php
use App\Support\Lang;
use App\Support\View;

/** @var array<string,mixed> $quote Row from QuoteModel::find(). */
/** @var array<int,array<string,mixed>> $lines */
/** @var string $generated_at */

$e     = static fn (?string $v): string => View::e($v);
$money = static fn ($v): string => '€ ' . number_format((float) $v, 2, ',', '.');
$date  = static fn (?string $v): string => $v ? date('d/m/Y', (int) strtotime($v)) : '—';
$qty   = static fn ($v): string => rtrim(rtrim((string) $v, '0'), '.');

$subtotal = 0.0;
foreach ($lines as $line) {
    $subtotal += (float) $line['qty'] * (float) $line['unit_price'];
}
$vatAmount = $subtotal * (float) $quote['vat_rate'] / 100;
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
        'doc_title'    => 'Preventivo n. ' . $quote['number'],
        'doc_subtitle' => $quote['title'],
    ], null) ?>

    <h2>Cliente</h2>
    <table class="data">
        <tr><th width="25%">Nome</th><td><?= $e($quote['client_name']) ?></td></tr>
        <?php if ($quote['client_vat']): ?>
            <tr><th>P.IVA / C.F.</th><td><?= $e($quote['client_vat']) ?></td></tr>
        <?php endif; ?>
        <?php if ($quote['client_address']): ?>
            <tr><th>Indirizzo</th><td><?= $e($quote['client_address']) ?></td></tr>
        <?php endif; ?>
        <?php if ($quote['client_email']): ?>
            <tr><th>Email</th><td><?= $e($quote['client_email']) ?></td></tr>
        <?php endif; ?>
    </table>

    <h2>Dettagli preventivo</h2>
    <table class="data">
        <tr><th width="25%">Data</th><td><?= $e($date($quote['quote_date'])) ?></td></tr>
        <?php if ($quote['valid_until']): ?>
            <tr><th>Valido fino al</th><td><?= $e($date($quote['valid_until'])) ?></td></tr>
        <?php endif; ?>
        <?php if ($quote['project_name']): ?>
            <tr><th>Cantiere</th><td><?= $e($quote['project_name']) ?></td></tr>
        <?php endif; ?>
        <tr><th>Stato</th><td><span class="status-badge"><?= $e(Lang::label('quote_status', $quote['status'])) ?></span></td></tr>
    </table>

    <h2>Voci</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Descrizione</th>
                <th class="num" width="10%">Quantità</th>
                <th width="10%">Unità</th>
                <th class="num" width="15%">Prezzo unitario</th>
                <th class="num" width="15%">Totale</th>
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
        <tr><td>Imponibile:</td><td><?= $e($money($subtotal)) ?></td></tr>
        <tr><td>IVA (<?= $e($qty($quote['vat_rate'])) ?>%):</td><td><?= $e($money($vatAmount)) ?></td></tr>
        <tr class="grand"><td>Totale:</td><td><?= $e($money($subtotal + $vatAmount)) ?></td></tr>
    </table>

    <?php if ($quote['notes']): ?>
        <h2>Note e condizioni</h2>
        <p><?= nl2br($e($quote['notes'])) ?></p>
    <?php endif; ?>

    <p class="muted" style="margin-top:12pt;">Documento generato il <?= $e($generated_at) ?></p>
</body>
</html>
