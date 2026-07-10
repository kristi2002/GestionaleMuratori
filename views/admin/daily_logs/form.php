<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed>|null $log always null (create only) */
/** @var array<int,array<string,mixed>> $projects */
/** @var int $projectId */
/** @var string $today */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
?>
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.daily_logs.new')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.daily_logs.subtitle')) ?></p>
    </div>
    <?= View::render('partials/back_button', ['href' => '/admin/daily-logs'], null) ?>
</div>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.daily_logs.title'), '/admin/daily-logs'],
    [$t('admin.daily_logs.new'), null],
]], null) ?>

<div class="card">
    <div class="card-body">
        <form class="js-crud-form"
              data-base-url="<?= $e(Url::to('/admin/daily-logs')) ?>"
              data-redirect="<?= $e(Url::to('/admin/daily-logs')) ?>">

            <div class="alert alert-danger d-none js-crud-error" role="alert"></div>

            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.daily_logs.project')) ?></label>
                <select class="form-select" name="project_id" required>
                    <?php foreach ($projects as $p): ?>
                        <option value="<?= $e((string) $p['id']) ?>" <?= $projectId === (int) $p['id'] ? 'selected' : '' ?>>
                            <?= $e($p['name']) ?> — <?= $e($p['client_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.daily_logs.date')) ?></label>
                    <input type="date" class="form-control" name="log_date" value="<?= $e($today) ?>" max="<?= $e($today) ?>" required>
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.daily_logs.workers')) ?></label>
                    <input type="number" min="0" step="1" class="form-control" name="workers_present">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.daily_logs.weather')) ?></label>
                <input type="text" class="form-control" name="weather_text" placeholder="<?= $e($t('admin.daily_logs.weather_auto')) ?>">
                <div class="form-text"><?= $e($t('admin.daily_logs.weather_help')) ?></div>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.daily_logs.work_done')) ?></label>
                <textarea class="form-control" name="work_done" rows="3"></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.daily_logs.notes')) ?></label>
                <textarea class="form-control" name="notes" rows="2"></textarea>
            </div>

            <div class="d-flex gap-2 pt-3 border-top">
                <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/daily-logs')) ?>"><?= $e($t('common.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
