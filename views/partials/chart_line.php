<?php
use App\Support\View;

/**
 * Line + area chart with gridlines, y-axis labels, dots and value labels
 * (pure SVG, no JS). For short time series such as interventions-per-month.
 *
 * @var array<int,array{label:string,value:int}> $points
 */
$e = static fn (?string $v): string => View::e($v);

$n    = count($points);
$maxV = 0;
foreach ($points as $p) {
    $maxV = max($maxV, (int) $p['value']);
}
$max = max(1, $maxV);

// Plot bounds within the viewBox.
$L = 11.0; $R = 103.0; $T = 6.0; $B = 48.0;
$xFor = static fn (int $i): float => $n <= 1 ? ($L + $R) / 2 : $L + ($R - $L) * $i / ($n - 1);
$yFor = static fn (float $v): float => $B - ($v / $max) * ($B - $T);

$linePts = [];
$area    = 'M ' . round($xFor(0), 2) . ',' . round($B, 2);
foreach ($points as $i => $p) {
    $x = round($xFor($i), 2);
    $y = round($yFor((float) $p['value']), 2);
    $linePts[] = $x . ',' . $y;
    $area     .= ' L ' . $x . ',' . $y;
}
$area .= ' L ' . round($xFor(max(0, $n - 1)), 2) . ',' . round($B, 2) . ' Z';
?>
<div class="app-linechart">
    <svg viewBox="0 0 108 58" class="app-linechart-svg" role="img" aria-hidden="true">
        <?php foreach ([0.0, 0.25, 0.5, 0.75, 1.0] as $frac): $gy = $B - $frac * ($B - $T); ?>
            <line x1="<?= $L ?>" y1="<?= round($gy, 2) ?>" x2="<?= $R ?>" y2="<?= round($gy, 2) ?>" class="app-chart-grid"></line>
            <text x="<?= $L - 2 ?>" y="<?= round($gy + 1.1, 2) ?>" text-anchor="end" class="app-chart-axis"><?= (int) round($max * $frac) ?></text>
        <?php endforeach; ?>
        <?php if ($maxV > 0): ?>
            <path d="<?= $e($area) ?>" class="app-linechart-area"></path>
            <polyline points="<?= $e(implode(' ', $linePts)) ?>" class="app-linechart-line"></polyline>
            <?php foreach ($points as $i => $p): $x = $xFor($i); $y = $yFor((float) $p['value']); ?>
                <circle cx="<?= round($x, 2) ?>" cy="<?= round($y, 2) ?>" r="1.3" class="app-linechart-dot"></circle>
                <?php if ((int) $p['value'] > 0): ?>
                    <text x="<?= round($x, 2) ?>" y="<?= round($y - 2.4, 2) ?>" text-anchor="middle" class="app-linechart-val"><?= (int) $p['value'] ?></text>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php foreach ($points as $i => $p): ?>
            <text x="<?= round($xFor($i), 2) ?>" y="<?= round($B + 6, 2) ?>" text-anchor="middle" class="app-chart-axis"><?= $e($p['label']) ?></text>
        <?php endforeach; ?>
    </svg>
</div>
