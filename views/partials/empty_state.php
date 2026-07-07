<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/**
 * Friendly empty state: render with
 * View::render('partials/empty_state', ['message' => ..., 'actions' => [[label, href, class?]]], null).
 * @var string $message
 * @var string|null $hint Optional second line (defaults to the generic filter hint)
 * @var array<int,array{0:string,1:string,2?:string}> $actions [label, href, optional btn class]
 */
$e = static fn (?string $v): string => View::e($v);
$actions = $actions ?? [];
?>
<div class="card">
    <div class="app-empty-state">
        <i class="bi bi-search" aria-hidden="true"></i>
        <p class="mb-1 fw-semibold"><?= $e($message) ?></p>
        <p class="small mb-3"><?= $e($hint ?? Lang::get('common.no_results_hint')) ?></p>
        <?php if ($actions !== []): ?>
            <div class="d-flex justify-content-center flex-wrap gap-2">
                <?php foreach ($actions as $action): ?>
                    <a class="btn <?= $e($action[2] ?? 'btn-outline-secondary') ?>" href="<?= $e(Url::to($action[1])) ?>">
                        <?= $e($action[0]) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
