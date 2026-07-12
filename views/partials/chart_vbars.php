<?php
use App\Support\View;

/**
 * Vertical bar chart for a short time series (SVG, no JS). Bars scale to the
 * largest value; month labels sit under the axis.
 *
 * @var array<int,array{label:string,value:int}> $points
 */
$e = static fn (?string $v): string => View::e($v);

$max = 1;
foreach ($points as $p) {
    $max = max($max, (int) $p['value']);
}
$n   = count($points);
$w   = 100.0;
$h   = 58.0;
$gap = 3.5;
$bw  = $n > 0 ? ($w - $gap * ($n + 1)) / $n : 0;
?>
<div class="app-vbars">
    <svg viewBox="0 0 <?= (int) $w ?> <?= (int) $h + 12 ?>" class="app-vbars-svg" role="img" aria-hidden="true">
        <line x1="0" y1="<?= $h ?>" x2="<?= $w ?>" y2="<?= $h ?>" class="app-vbars-axis"></line>
        <?php foreach ($points as $i => $p):
            $val = (int) $p['value'];
            $bh  = $val / $max * ($h - 6);
            $x   = $gap + $i * ($bw + $gap);
            $y   = $h - $bh;
        ?>
            <rect x="<?= round($x, 2) ?>" y="<?= round($y, 2) ?>" width="<?= round($bw, 2) ?>" height="<?= round($bh, 2) ?>" rx="1" class="app-vbars-bar"></rect>
            <?php if ($val > 0): ?>
                <text x="<?= round($x + $bw / 2, 2) ?>" y="<?= round($y - 1.5, 2) ?>" text-anchor="middle" class="app-vbars-num"><?= $val ?></text>
            <?php endif; ?>
            <text x="<?= round($x + $bw / 2, 2) ?>" y="<?= round($h + 8, 2) ?>" text-anchor="middle" class="app-vbars-label"><?= $e($p['label']) ?></text>
        <?php endforeach; ?>
    </svg>
</div>
