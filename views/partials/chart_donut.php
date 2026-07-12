<?php
use App\Support\View;

/**
 * Pure-SVG donut chart (no JS, CSP-safe). Segments are drawn with the
 * stroke-dasharray technique on a circumference-100 circle.
 *
 * @var array<int,array{label:string,value:int|float,color:string,display?:string}> $segments
 * @var string $centerNum  big number in the hole (e.g. the total)
 * @var string $empty      text shown when there is no data
 */
$e = static fn (?string $v): string => View::e($v);

$total = 0.0;
foreach ($segments as $s) {
    $total += (float) $s['value'];
}
?>
<div class="app-chart-donut">
    <svg viewBox="0 0 42 42" class="app-donut" role="img" aria-hidden="true">
        <circle cx="21" cy="21" r="15.91549" fill="none" class="app-donut-track" stroke-width="5"></circle>
        <?php if ($total > 0): $accum = 0.0; ?>
            <?php foreach ($segments as $s): $val = (float) $s['value']; ?>
                <?php if ($val > 0):
                    $pct = $val / $total * 100;
                    $rot = $accum / $total * 360 - 90;
                    $accum += $val;
                ?>
                    <circle cx="21" cy="21" r="15.91549" fill="none"
                            stroke="<?= $e($s['color']) ?>" stroke-width="5"
                            stroke-dasharray="<?= round($pct, 3) ?> <?= round(100 - $pct, 3) ?>"
                            transform="rotate(<?= round($rot, 3) ?> 21 21)"></circle>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
        <text x="21" y="23" text-anchor="middle" class="app-donut-num"><?= $e($centerNum ?? (string) (int) $total) ?></text>
    </svg>
    <?php if ($total <= 0): ?>
        <p class="text-muted small mb-0 mt-2"><?= $e($empty ?? '—') ?></p>
    <?php else: ?>
        <ul class="app-chart-legend">
            <?php foreach ($segments as $s): $pct = $total > 0 ? (float) $s['value'] / $total * 100 : 0; ?>
                <li>
                    <span class="app-legend-dot" style="background:<?= $e($s['color']) ?>"></span>
                    <span class="app-legend-label"><?= $e((string) $s['label']) ?></span>
                    <span class="app-legend-val"><?= $e((string) ($s['display'] ?? (string) $s['value'])) ?></span>
                    <span class="app-legend-pct"><?= $e(number_format($pct, $pct < 10 ? 1 : 0, ',', '.')) ?>%</span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
