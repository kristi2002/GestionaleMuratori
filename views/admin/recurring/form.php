<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed>|null $plan null = create, row = edit */
/** @var array<int,array<string,mixed>> $projects */
/** @var array<int,array<string,mixed>> $workers */
/** @var array<int,string> $frequencies */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$isEdit    = $plan !== null;
$pageTitle = $isEdit ? $t('admin.recurring.edit') : $t('admin.recurring.new');
$value     = static fn (string $key): string => (string) ($plan[$key] ?? '');
?>
<?php
echo View::render('partials/page_head', [
    'title'    => $pageTitle,
    'subtitle' => $isEdit ? (string) $plan['title'] : $t('admin.recurring.subtitle'),
    'actions'  => View::render('partials/back_button', ['href' => '/admin/interventions/recurring'], null),
], null);
?>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.interventions.title'), '/admin/interventions'],
    [$t('admin.recurring.title'), '/admin/interventions/recurring'],
    [$isEdit ? (string) $plan['title'] : $t('admin.recurring.new'), null],
]], null) ?>

<div class="card">
    <div class="card-body">
        <form class="js-crud-form"
              data-base-url="<?= $e(Url::to('/admin/interventions/recurring')) ?>"
              data-redirect="<?= $e(Url::to('/admin/interventions/recurring')) ?>">
            <input type="hidden" name="id" value="<?= $e($isEdit ? (string) $plan['id'] : '') ?>">

            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.interventions.project')) ?></label>
                    <select class="form-select" name="project_id" required>
                        <option value="">—</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $e((string) $p['id']) ?>" <?= (int) $value('project_id') === (int) $p['id'] ? 'selected' : '' ?>>
                                <?= $e($p['name']) ?> (<?= $e($p['client_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.interventions.worker')) ?></label>
                    <select class="form-select" name="assigned_worker_id">
                        <option value=""><?= $e($t('admin.interventions.unassigned')) ?></option>
                        <?php foreach ($workers as $w): ?>
                            <option value="<?= $e((string) $w['id']) ?>" <?= (int) $value('assigned_worker_id') === (int) $w['id'] ? 'selected' : '' ?>>
                                <?= $e($w['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.interventions.field_title')) ?></label>
                <input type="text" class="form-control" name="title" maxlength="190" value="<?= $e($value('title')) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.interventions.description')) ?></label>
                <textarea class="form-control" name="description" rows="2"><?= $e($value('description')) ?></textarea>
            </div>

            <div class="row">
                <div class="col-6 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.recurring.frequency')) ?></label>
                    <select class="form-select" name="frequency" required>
                        <?php foreach ($frequencies as $f): ?>
                            <option value="<?= $e($f) ?>" <?= $value('frequency') === $f ? 'selected' : '' ?>><?= $e($t('admin.recurring.freq_' . $f)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.recurring.interval')) ?></label>
                    <input type="number" min="1" max="52" class="form-control" name="interval_count"
                           value="<?= $e($value('interval_count') !== '' ? $value('interval_count') : '1') ?>" required>
                    <div class="form-text"><?= $e($t('admin.recurring.interval_help')) ?></div>
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.recurring.start_date')) ?></label>
                    <input type="date" class="form-control" name="start_date" value="<?= $e($value('start_date')) ?>" required>
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.recurring.end_date')) ?></label>
                    <input type="date" class="form-control" name="end_date" value="<?= $e($value('end_date')) ?>">
                    <div class="form-text"><?= $e($t('admin.recurring.end_date_help')) ?></div>
                </div>
            </div>

            <div class="mb-3" style="max-width:12rem;">
                <label class="form-label"><?= $e($t('admin.interventions.scheduled_time')) ?></label>
                <input type="time" class="form-control" name="scheduled_start_time"
                       value="<?= $e($value('scheduled_start_time') !== '' ? substr($value('scheduled_start_time'), 0, 5) : '') ?>">
            </div>

            <div class="alert alert-danger d-none js-crud-error" role="alert"></div>

            <div class="d-flex gap-2 pt-3 border-top">
                <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/interventions/recurring')) ?>"><?= $e($t('common.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
