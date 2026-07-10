<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed>|null $sal always null — create-only form */
/** @var array<int,array<string,mixed>> $projects */
/** @var int $projectId */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
?>
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.sal.new')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.sal.subtitle')) ?></p>
    </div>
    <?= View::render('partials/back_button', ['href' => '/admin/sal'], null) ?>
</div>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.sal.title'), '/admin/sal'],
    [$t('admin.sal.new'), null],
]], null) ?>

<div class="card">
    <div class="card-body">
        <form class="js-crud-form"
              data-base-url="<?= $e(Url::to('/admin/sal')) ?>"
              data-redirect="<?= $e(Url::to('/admin/sal')) ?>">
            <input type="hidden" name="id" value="">

            <div class="alert alert-danger d-none js-crud-error" role="alert"></div>

            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.sal.project')) ?></label>
                <select class="form-select" name="project_id">
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $e((string) $p['id']) ?>" <?= $projectId === (int) $p['id'] ? 'selected' : '' ?>>
                            <?= $e($p['name']) ?> — <?= $e($p['client_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.sal.period_from')) ?></label>
                    <input type="date" class="form-control" name="period_from">
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.sal.period_to')) ?></label>
                    <input type="date" class="form-control" name="period_to">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.sal.description')) ?></label>
                <textarea class="form-control" name="description" rows="3"></textarea>
            </div>

            <div class="d-flex gap-2 pt-3 border-top">
                <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/sal')) ?>"><?= $e($t('common.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
