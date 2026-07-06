<?php
use App\Support\Lang;
use App\Support\View;

/** @var array<string,mixed> $document */
/** @var array<int,array<string,mixed>> $lines */
/** @var string|null $signatureSrc  file:/// src of the DL signature, when signed */

$e = static fn (?string $v): string => View::e($v);
$money = static fn ($v): string => number_format((float) $v, 2, ',', '.') . ' €';
$qty = static fn ($v): string => rtrim(rtrim((string) $v, '0'), '.');
$signatureSrc = $signatureSrc ?? null;
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<style>
    body { font-family: sans-serif; font-size: 11pt; color: #222; }
    h1 { font-size: 16pt; margin: 0 0 2pt; }
    .muted { color: #666; font-size: 9pt; }
    .header-table td { vertical-align: top; padding: 1pt 0; }
    table.data { width: 100%; border-collapse: collapse; margin-top: 4pt; }
    table.data th, table.data td { border: 1px solid #ccc; padding: 4pt 6pt; font-size: 9.5pt; text-align: left; }
    table.data th { background: #f0f0f0; }
    table.lines th, table.lines td { border: 1px solid #ccc; padding: 4pt 6pt; font-size: 9.5pt; }
    table.lines { width: 100%; border-collapse: collapse; margin-top: 6pt; }
    table.lines th { background: #f0f0f0; }
    .num { text-align: right; }
    .total-row td { font-weight: bold; background: #f7f7f7; }
    .sign-box { margin-top: 28pt; width: 100%; }
    .sign-box td { width: 50%; vertical-align: top; padding-top: 6pt; }
    .sign-line { border-top: 1px solid #333; margin-top: 40pt; padding-top: 3pt; font-size: 9pt; }
    .sign-img img { max-width: 180pt; max-height: 60pt; }
</style>
</head>
<body>
    <table class="header-table" width="100%">
        <tr>
            <td width="70%">
                <h1>Stato Avanzamento Lavori n. <?= $e((string) $document['number']) ?></h1>
                <div class="muted"><?= $e(Lang::get('app_name')) ?> — S.A.L.</div>
            </td>
            <td width="30%" align="right" class="muted">
                Stato: <?= $e(Lang::label('sal_status', $document['status'])) ?><br>
                <?php if ($document['issued_at']): ?>Emesso il <?= $e(substr((string) $document['issued_at'], 0, 10)) ?><?php endif; ?>
            </td>
        </tr>
    </table>

    <table class="data" style="margin-top:8pt;">
        <tr><th width="25%">Cantiere</th><td><?= $e($document['project_name']) ?><?= $document['project_location'] ? ' — ' . $e($document['project_location']) : '' ?></td></tr>
        <tr><th>Committente</th><td><?= $e($document['client_name']) ?><?= $document['client_vat'] ? ' (' . $e($document['client_vat']) . ')' : '' ?></td></tr>
        <tr><th>Periodo</th><td><?= $e($document['period_from'] ?? '—') ?><?= $document['period_to'] ? ' — ' . $e($document['period_to']) : '' ?></td></tr>
        <?php if ($document['description']): ?>
            <tr><th>Descrizione</th><td><?= nl2br($e($document['description'])) ?></td></tr>
        <?php endif; ?>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th width="46%">Descrizione</th>
                <th class="num">Quantità</th>
                <th>U.M.</th>
                <th class="num">Prezzo unit.</th>
                <th class="num">Importo</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($lines === []): ?>
            <tr><td colspan="5" class="muted">Nessuna voce.</td></tr>
        <?php endif; ?>
        <?php foreach ($lines as $l): ?>
            <tr>
                <td><?= $e($l['description']) ?></td>
                <td class="num"><?= $e($qty($l['qty'])) ?></td>
                <td><?= $e($l['unit']) ?></td>
                <td class="num"><?= $e($money($l['unit_price'])) ?></td>
                <td class="num"><?= $e($money($l['amount'])) ?></td>
            </tr>
        <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="4" class="num">Totale S.A.L.</td>
                <td class="num"><?= $e($money($document['amount'])) ?></td>
            </tr>
        </tbody>
    </table>

    <table class="sign-box">
        <tr>
            <td>
                <div class="muted">L'Impresa</div>
                <div class="sign-line">Timbro e firma</div>
            </td>
            <td>
                <div class="muted">Il Direttore dei Lavori</div>
                <?php if ($signatureSrc !== null): ?>
                    <div class="sign-img"><img src="<?= $e($signatureSrc) ?>" alt="Firma DL"></div>
                    <div class="sign-line">Firmato il <?= $e(substr((string) $document['signed_at'], 0, 10)) ?></div>
                <?php else: ?>
                    <div class="sign-line">Firma per approvazione</div>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</body>
</html>
