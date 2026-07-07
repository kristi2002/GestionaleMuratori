<?php
use App\Support\Config;
use App\Support\View;

/**
 * Intestazione condivisa dei documenti PDF: logo + dati aziendali a sinistra,
 * titolo del documento a destra. I dati arrivano da config('company.*') / .env.
 *
 * @var string      $doc_title    es. "Preventivo n. 2026/001"
 * @var string|null $doc_subtitle riga secondaria sotto il titolo
 */

$e = static fn (?string $v): string => View::e($v);

$companyName = (string) Config::get('company.name', '');
$addressVat  = implode(' — ', array_filter([
    (string) Config::get('company.address', ''),
    (string) Config::get('company.vat', ''),
]));
$contact = implode(' · ', array_filter([
    (string) Config::get('company.phone', ''),
    (string) Config::get('company.email', ''),
]));
$logoPath = dirname(__DIR__, 3) . '/public/assets/img/logo_print.png';
?>
<table class="header-table" width="100%">
    <tr>
        <td width="58%">
            <table cellpadding="0" cellspacing="0">
                <tr>
                    <?php if (is_file($logoPath)): ?>
                        <td style="vertical-align: middle;"><img src="<?= $e($logoPath) ?>" width="40" height="40"></td>
                    <?php endif; ?>
                    <td style="vertical-align: middle; padding-left: 7pt;">
                        <div style="font-size: 13pt; font-weight: bold; color: #1b5e20;"><?= $e($companyName) ?></div>
                        <?php if ($addressVat !== ''): ?><div class="muted"><?= $e($addressVat) ?></div><?php endif; ?>
                        <?php if ($contact !== ''): ?><div class="muted"><?= $e($contact) ?></div><?php endif; ?>
                    </td>
                </tr>
            </table>
        </td>
        <td width="42%" align="right" style="vertical-align: middle;">
            <h1><?= $e($doc_title) ?></h1>
            <?php if (!empty($doc_subtitle)): ?><div class="muted"><?= $e($doc_subtitle) ?></div><?php endif; ?>
        </td>
    </tr>
</table>
<div style="border-bottom: 2px solid #2e7d32; margin: 6pt 0 8pt;"></div>
