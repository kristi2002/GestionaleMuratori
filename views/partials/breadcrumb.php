<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/**
 * Breadcrumb trail: render with View::render('partials/breadcrumb', ['items' => ...], null).
 * @var array<int,array{0:string,1:?string}> $items [label, href] — null href marks the current page.
 */
$e = static fn (?string $v): string => View::e($v);
?>
<nav aria-label="<?= $e(Lang::get('nav.breadcrumb')) ?>" class="mb-3">
    <ol class="breadcrumb mb-0">
        <?php foreach ($items as [$label, $href]): ?>
            <?php if ($href !== null): ?>
                <li class="breadcrumb-item"><a href="<?= $e(Url::to($href)) ?>"><?= $e($label) ?></a></li>
            <?php else: ?>
                <li class="breadcrumb-item active" aria-current="page"><?= $e($label) ?></li>
            <?php endif; ?>
        <?php endforeach; ?>
    </ol>
</nav>
