<?php
use App\Support\Url;
use App\Support\View;

/**
 * Pill filter tabs (Tutti / Attivi / … ) matching the mockups. Each pill is a
 * link that re-loads the list with the filter applied. Render with
 *   View::render('partials/filter_pills', ['pills' => [
 *       ['label' => 'Tutti', 'href' => '/admin/projects', 'active' => true],
 *       ['label' => 'Attivi', 'href' => '/admin/projects?status=active',
 *        'active' => false, 'count' => 12, 'dot' => 'success'],
 *   ]], null)
 * @var array<int,array{label:string,href:string,active?:bool,count?:int|string,dot?:string}> $pills
 */
$e = static fn (?string $v): string => View::e($v);
$pills = $pills ?? [];
?>
<?php if ($pills !== []): ?>
<div class="app-pills" role="tablist">
    <?php foreach ($pills as $p): ?>
        <?php $active = !empty($p['active']); ?>
        <a class="app-pill<?= $active ? ' active' : '' ?>" href="<?= $e(Url::to($p['href'])) ?>"
           <?= $active ? 'aria-current="page"' : '' ?>>
            <?php if (isset($p['dot'])): ?>
                <span class="app-pill-dot text-<?= $e($p['dot']) ?>" aria-hidden="true"></span>
            <?php endif; ?>
            <span><?= $e($p['label']) ?></span>
            <?php if (isset($p['count']) && $p['count'] !== null && $p['count'] !== ''): ?>
                <span class="app-pill-count"><?= $e((string) $p['count']) ?></span>
            <?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
