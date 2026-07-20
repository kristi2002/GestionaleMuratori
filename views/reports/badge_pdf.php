<?php
use App\Support\Lang;
use App\Support\View;

/** @var array<string,mixed> $worker  users row */
/** @var array<string,mixed> $company company_settings row */
/** @var string|null $photo   data: URI of the worker avatar, or null */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$date = static fn (?string $v): string => $v ? date('d/m/Y', (int) strtotime($v)) : '—';
$dash = static fn (?string $v): string => ($v !== null && $v !== '') ? $v : '—';
?>
<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="utf-8">
<style>
    body { font-family: sans-serif; color: #12233b; margin: 0; }
    .badge { border: 1.2pt solid #0b2545; border-radius: 4pt; padding: 8pt; text-align: center; }
    .employer { font-size: 11pt; font-weight: bold; color: #0b2545; border-bottom: 1pt solid #d97a2b; padding-bottom: 4pt; margin-bottom: 6pt; }
    .kicker { font-size: 6.5pt; letter-spacing: 0.5pt; color: #64748b; text-transform: uppercase; margin-bottom: 6pt; }
    .photo { width: 40mm; height: 48mm; object-fit: cover; border: 1pt solid #cbd5e1; margin: 0 auto 6pt; }
    .photo-empty { width: 40mm; height: 48mm; border: 1pt dashed #cbd5e1; margin: 0 auto 6pt; line-height: 48mm; color: #94a3b8; font-size: 7pt; }
    .name { font-size: 12pt; font-weight: bold; margin: 2pt 0; }
    .role { font-size: 8.5pt; color: #475569; margin-bottom: 6pt; }
    table.meta { width: 100%; font-size: 7.5pt; border-top: 1pt solid #e2e8f0; margin-top: 4pt; }
    table.meta td { padding: 2pt 0; text-align: left; }
    table.meta td.label { color: #64748b; }
    table.meta td.value { text-align: right; font-weight: bold; }
    .legal { font-size: 5.5pt; color: #94a3b8; margin-top: 6pt; }
</style>
</head>
<body>
    <div class="badge">
        <div class="employer"><?= $e($dash($company['denominazione'] ?? null)) ?></div>
        <div class="kicker"><?= $e($t('report.badge_kicker')) ?></div>

        <?php if ($photo !== null): ?>
            <img class="photo" src="<?= $e($photo) ?>" alt="">
        <?php else: ?>
            <div class="photo-empty"><?= $e($t('report.badge_no_photo')) ?></div>
        <?php endif; ?>

        <div class="name"><?= $e((string) $worker['name']) ?></div>
        <div class="role"><?= $e($dash($worker['job_title'] ?? null)) ?></div>

        <table class="meta">
            <tr>
                <td class="label"><?= $e($t('report.badge_hire_date')) ?></td>
                <td class="value"><?= $e($date($worker['hire_date'] ?? null)) ?></td>
            </tr>
            <?php if (!empty($company['partita_iva'])): ?>
            <tr>
                <td class="label"><?= $e($t('report.badge_employer_vat')) ?></td>
                <td class="value"><?= $e((string) $company['partita_iva']) ?></td>
            </tr>
            <?php endif; ?>
        </table>

        <div class="legal"><?= $e($t('report.badge_legal')) ?></div>
    </div>
</body>
</html>
