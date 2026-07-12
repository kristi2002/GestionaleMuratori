<?php
use App\Support\View;

/**
 * Horizontal bar chart (HTML/CSS, no JS). Each row: label, proportional bar,
 * value. Scaled to the largest value.
 *
 * @var array<int,array{label:string,value:int|float,display?:string,color?:string}> $items
 * @var string $empty  text shown when there are no rows
 */
$e = static fn (?string $v): string => View::e($v);

$max = 0.0;
foreach ($items as $it) {
    $max = max($max, (float) $it['value']);
}
?>
<?php if ($items === []): ?>
    <p class="text-muted small mb-0"><?= $e($empty ?? '—') ?></p>
<?php else: ?>
    <div class="app-hbars">
        <?php foreach ($items as $it): $pct = $max > 0 ? (float) $it['value'] / $max * 100 : 0; ?>
            <div class="app-hbar-row">
                <div class="app-hbar-label" title="<?= $e((string) $it['label']) ?>"><?= $e((string) $it['label']) ?></div>
                <div class="app-hbar-track">
                    <div class="app-hbar-fill" style="width:<?= round($pct, 1) ?>%;background:<?= $e($it['color'] ?? 'var(--app-green)') ?>"></div>
                </div>
                <div class="app-hbar-val"><?= $e((string) ($it['display'] ?? (string) $it['value'])) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
