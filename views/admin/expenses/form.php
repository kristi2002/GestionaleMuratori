<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed>|null $expense null = create, row = edit */
/** @var array<int,array<string,mixed>> $workers */
/** @var array<int,array<string,mixed>> $projects */
/** @var array<int,string> $categories */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$isEdit    = $expense !== null;
$pageTitle = $isEdit ? $t('admin.expenses.edit') : $t('admin.expenses.new');
$value     = static fn (string $key): string => (string) ($expense[$key] ?? '');
?>
<?php
echo View::render('partials/page_head', [
    'title'    => $pageTitle,
    'subtitle' => $isEdit ? (string) $expense['description'] : $t('admin.expenses.subtitle'),
    'actions'  => View::render('partials/back_button', ['href' => '/admin/expenses', 'label' => $t('admin.expenses.back_to_list')], null),
], null);
?>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.expenses.title'), '/admin/expenses'],
    [$isEdit ? (string) $expense['description'] : $t('admin.expenses.new'), null],
]], null) ?>

<div class="card">
    <div class="card-body">
        <form class="js-crud-form"
              data-base-url="<?= $e(Url::to('/admin/expenses')) ?>"
              data-redirect="<?= $e(Url::to('/admin/expenses')) ?>">
            <input type="hidden" name="id" value="<?= $e($isEdit ? (string) $expense['id'] : '') ?>">

            <h2 class="app-form-section"><?= $e($t('admin.expenses.section_main')) ?></h2>
            <div class="row">
                <div class="col-6 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.expenses.date')) ?></label>
                    <input type="date" class="form-control" name="expense_date"
                           value="<?= $e($isEdit ? (string) $expense['expense_date'] : date('Y-m-d')) ?>" required>
                </div>
                <div class="col-6 col-md-4 mb-3">
                    <label class="form-label"><?= $e($t('admin.expenses.category')) ?></label>
                    <select class="form-select" name="category" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $e($cat) ?>" <?= $isEdit && $expense['category'] === $cat ? 'selected' : '' ?>>
                                <?= $e(Lang::label('expense_categories', $cat)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-5 mb-3">
                    <label class="form-label"><?= $e($t('admin.expenses.amount')) ?> (€)</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" name="amount"
                           value="<?= $e($value('amount')) ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.expenses.description')) ?></label>
                <input type="text" class="form-control" name="description" value="<?= $e($value('description')) ?>"
                       placeholder="<?= $e($t('admin.expenses.description_placeholder')) ?>" required>
            </div>

            <h2 class="app-form-section"><?= $e($t('admin.expenses.section_links')) ?></h2>
            <p class="text-muted small"><?= $e($t('admin.expenses.links_hint')) ?></p>
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.expenses.worker_optional')) ?></label>
                    <select class="form-select" name="worker_id">
                        <option value="">—</option>
                        <?php foreach ($workers as $w): ?>
                            <option value="<?= $e((string) $w['id']) ?>"
                                    <?= $isEdit && (int) ($expense['worker_id'] ?? 0) === (int) $w['id'] ? 'selected' : '' ?>>
                                <?= $e($w['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.expenses.project_optional')) ?></label>
                    <select class="form-select" name="project_id">
                        <option value="">—</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $e((string) $p['id']) ?>"
                                    <?= $isEdit && (int) ($expense['project_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                                <?= $e($p['name']) ?> (<?= $e($p['client_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.expenses.note')) ?></label>
                <input type="text" class="form-control" name="note" value="<?= $e($value('note')) ?>"
                       placeholder="<?= $e($t('admin.expenses.note_placeholder')) ?>">
            </div>

            <div class="alert alert-danger d-none js-crud-error" role="alert"></div>

            <div class="d-flex gap-2 pt-3 border-top">
                <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/expenses')) ?>"><?= $e($t('common.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
