<?php
use App\Support\Lang;
use App\Support\View;

/** @var array<string,mixed> $project */
/** @var array<int,array<string,mixed>> $interventions */
/** @var array<int,array<string,mixed>> $materials */
/** @var array{count:int,completed:int} $totals */
/** @var string $generated_at */

$e = static fn (?string $v): string => View::e($v);
$fileSrc = static function (?string $absolutePath): string {
    return $absolutePath === null ? '' : 'file:///' . str_replace('\\', '/', $absolutePath);
};
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
    .logo-placeholder {
        width: 70pt; height: 36pt; border: 1px dashed #aaa; color: #aaa;
        font-size: 8pt; text-align: center; line-height: 36pt;
    }
    table.data { width: 100%; border-collapse: collapse; margin-top: 4pt; }
    table.data th, table.data td { border: 1px solid #ccc; padding: 4pt 6pt; font-size: 9.5pt; text-align: left; }
    table.data th { background: #f0f0f0; }
    .status-badge { font-size: 8.5pt; padding: 1pt 5pt; border: 1px solid #999; border-radius: 8pt; }
    .intervention-block { page-break-inside: avoid; margin-bottom: 10pt; border: 1px solid #ddd; padding: 6pt 8pt; }
    .gallery img { width: 80pt; height: 80pt; object-fit: cover; margin: 3pt; border: 1px solid #ccc; }
    .signature img { max-width: 160pt; max-height: 60pt; border: 1px solid #ccc; margin-top: 3pt; }
</style>
</head>
<body>
    <table class="header-table" width="100%">
        <tr>
            <td width="70%">
                <h1><?= $e($project['name']) ?></h1>
                <div class="muted"><?= $e(Lang::get('app_name')) ?> — Report progetto</div>
            </td>
            <td width="30%" align="right"><div class="logo-placeholder">LOGO</div></td>
        </tr>
    </table>

    <table class="data" style="margin-top:8pt;">
        <tr><th width="25%">Cliente</th><td><?= $e($project['client_name']) ?></td></tr>
        <tr><th>Località</th><td><?= $e($project['location']) ?></td></tr>
        <tr><th>Periodo</th><td><?= $e($project['start_date']) ?><?= $project['end_date'] ? ' — ' . $e($project['end_date']) : '' ?></td></tr>
        <tr><th>Riferimento fattura</th><td><?= $e($project['invoice_reference']) ?></td></tr>
        <tr><th>Stato</th><td><?= $e(Lang::label('project_status', $project['status'])) ?></td></tr>
    </table>

    <h2>Interventi (<?= $e((string) $totals['count']) ?> totali, <?= $e((string) $totals['completed']) ?> completati)</h2>
    <table class="data">
        <thead>
            <tr>
                <th>Titolo</th>
                <th>Data</th>
                <th>Operaio</th>
                <th>Stato</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($interventions as $iv): ?>
            <tr>
                <td><?= $e($iv['title']) ?></td>
                <td><?= $e($iv['scheduled_date']) ?></td>
                <td><?= $e($iv['worker_name'] ?? '—') ?></td>
                <td><span class="status-badge"><?= $e(Lang::label('intervention_status', $iv['status'])) ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Materiali utilizzati</h2>
    <?php if ($materials === []): ?>
        <p class="muted">Nessun materiale registrato come utilizzato.</p>
    <?php else: ?>
        <table class="data">
            <thead><tr><th>Articolo</th><th>Unità</th><th>Quantità totale</th></tr></thead>
            <tbody>
            <?php foreach ($materials as $m): ?>
                <tr>
                    <td><?= $e($m['item_name']) ?></td>
                    <td><?= $e(Lang::label('units', $m['unit'])) ?></td>
                    <td><?= $e(rtrim(rtrim((string) $m['total_qty'], '0'), '.')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2>Dettaglio interventi e foto</h2>
    <?php foreach ($interventions as $iv): ?>
        <div class="intervention-block">
            <strong><?= $e($iv['title']) ?></strong>
            <span class="status-badge"><?= $e(Lang::label('intervention_status', $iv['status'])) ?></span>
            <?php if ($iv['completion_notes']): ?>
                <p class="muted"><?= $e($iv['completion_notes']) ?></p>
            <?php endif; ?>

            <?php if ($iv['gallery'] === []): ?>
                <p class="muted">Nessuna foto disponibile.</p>
            <?php else: ?>
                <div class="gallery">
                    <?php foreach ($iv['gallery'] as $photo): ?>
                        <?php if ($photo['absolute_path'] !== null): ?>
                            <img src="<?= $e($fileSrc($photo['absolute_path'])) ?>" alt="<?= $e(Lang::label('photo_types', $photo['type'])) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($iv['signature_absolute_path'] !== null): ?>
                <div class="signature">
                    <div class="muted">Firma cliente</div>
                    <img src="<?= $e($fileSrc($iv['signature_absolute_path'])) ?>" alt="Firma cliente">
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    <p class="muted" style="margin-top:12pt;">Report generato il <?= $e($generated_at) ?></p>
</body>
</html>
