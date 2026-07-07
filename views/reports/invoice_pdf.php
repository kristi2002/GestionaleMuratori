<?php
use App\Support\Lang;
use App\Support\View;

/** @var array<string,mixed> $invoice Row from ProjectInvoiceModel::findWithDetails(). */
/** @var string $generated_at */

$e     = static fn (?string $v): string => View::e($v);
$money = static fn ($v): string => $v === null ? '—' : '€ ' . number_format((float) $v, 2, ',', '.');
$date  = static fn (?string $v): string => $v ? date('d/m/Y', (int) strtotime($v)) : '—';
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
        'doc_title'    => 'Fattura n. ' . $invoice['number'],
        'doc_subtitle' => 'Ricevuta / riepilogo fattura',
    ], null) ?>

    <h2>Cliente</h2>
    <table class="data">
        <tr><th width="25%">Nome</th><td><?= $e($invoice['client_name']) ?></td></tr>
        <?php if ($invoice['client_vat']): ?>
            <tr><th>P.IVA / C.F.</th><td><?= $e($invoice['client_vat']) ?></td></tr>
        <?php endif; ?>
        <?php if ($invoice['client_address']): ?>
            <tr><th>Indirizzo</th><td><?= $e($invoice['client_address']) ?></td></tr>
        <?php endif; ?>
        <?php if ($invoice['client_email']): ?>
            <tr><th>Email</th><td><?= $e($invoice['client_email']) ?></td></tr>
        <?php endif; ?>
    </table>

    <h2>Dettagli fattura</h2>
    <table class="data">
        <tr><th width="25%">Numero</th><td><?= $e($invoice['number']) ?></td></tr>
        <tr><th>Data emissione</th><td><?= $e($date($invoice['issue_date'])) ?></td></tr>
        <tr><th>Cantiere</th>
            <td>
                <?= $e($invoice['project_name']) ?>
                <?= $invoice['project_location'] ? ' — ' . $e($invoice['project_location']) : '' ?>
            </td>
        </tr>
        <tr><th>Stato</th><td><span class="status-badge"><?= $e(Lang::label('invoice_status', $invoice['status'])) ?></span></td></tr>
        <?php if ($invoice['note']): ?>
            <tr><th>Nota</th><td><?= $e($invoice['note']) ?></td></tr>
        <?php endif; ?>
        <tr><th>Registrata da</th><td><?= $e($invoice['created_by_name']) ?></td></tr>
    </table>

    <div class="total-box">Importo: <?= $e($money($invoice['amount'])) ?></div>

    <p class="muted" style="margin-top:12pt;">Documento generato il <?= $e($generated_at) ?></p>
</body>
</html>
