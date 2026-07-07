<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed>|null $quote null = create, row = edit */
/** @var array<int,array<string,mixed>> $lines Existing line items (edit only) */
/** @var array<int,array<string,mixed>> $clients */
/** @var array<int,array<string,mixed>> $projects */
/** @var array<int,string> $statuses */
/** @var string $suggestedNumber */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$isEdit    = $quote !== null;
$pageTitle = $isEdit ? $t('admin.quotes.edit') : $t('admin.quotes.new');
$value     = static fn (string $key): string => (string) ($quote[$key] ?? '');
$num       = static fn ($v): string => rtrim(rtrim((string) $v, '0'), '.');
?>
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($pageTitle) ?></h1>
        <p class="text-muted mb-0"><?= $e($isEdit ? $quote['number'] . ' — ' . $quote['title'] : $t('admin.quotes.subtitle')) ?></p>
    </div>
    <?= View::render('partials/back_button', ['href' => '/admin/quotes', 'label' => $t('admin.quotes.back_to_list')], null) ?>
</div>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.quotes.title'), '/admin/quotes'],
    [$isEdit ? (string) $quote['number'] : $t('admin.quotes.new'), null],
]], null) ?>

<div class="card">
    <div class="card-body">
        <form class="js-crud-form"
              data-base-url="<?= $e(Url::to('/admin/quotes')) ?>"
              data-redirect="<?= $e(Url::to('/admin/quotes')) ?>">
            <input type="hidden" name="id" value="<?= $e($isEdit ? (string) $quote['id'] : '') ?>">

            <h2 class="app-form-section"><?= $e($t('admin.quotes.section_main')) ?></h2>
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.projects.client')) ?></label>
                    <select class="form-select js-quote-client" name="client_id" required>
                        <option value="">—</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $e((string) $c['id']) ?>"
                                    <?= $isEdit && (int) $quote['client_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= $e($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.quotes.project_optional')) ?></label>
                    <select class="form-select js-quote-project" name="project_id">
                        <option value="">—</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $e((string) $p['id']) ?>" data-client="<?= $e((string) $p['client_id']) ?>"
                                    <?= $isEdit && (int) ($quote['project_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                                <?= $e($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-12 col-md-4 mb-3">
                    <label class="form-label"><?= $e($t('admin.quotes.number')) ?></label>
                    <input type="text" class="form-control" name="number"
                           value="<?= $e($isEdit ? (string) $quote['number'] : $suggestedNumber) ?>" required>
                </div>
                <div class="col-12 col-md-8 mb-3">
                    <label class="form-label"><?= $e($t('admin.quotes.field_title')) ?></label>
                    <input type="text" class="form-control" name="title" value="<?= $e($value('title')) ?>"
                           placeholder="<?= $e($t('admin.quotes.title_placeholder')) ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-6 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.quotes.date')) ?></label>
                    <input type="date" class="form-control" name="quote_date"
                           value="<?= $e($isEdit ? (string) $quote['quote_date'] : date('Y-m-d')) ?>" required>
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.quotes.valid_until')) ?></label>
                    <input type="date" class="form-control" name="valid_until" value="<?= $e($value('valid_until')) ?>">
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.quotes.status')) ?></label>
                    <select class="form-select" name="status">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $e($s) ?>"
                                    <?= ($isEdit ? $quote['status'] : 'draft') === $s ? 'selected' : '' ?>>
                                <?= $e(Lang::label('quote_status', $s)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.quotes.vat_rate')) ?></label>
                    <input type="number" step="0.01" min="0" max="100" class="form-control js-quote-vat" name="vat_rate"
                           value="<?= $e($isEdit ? $num($quote['vat_rate']) : '22') ?>">
                </div>
            </div>

            <h2 class="app-form-section"><?= $e($t('admin.quotes.section_lines')) ?></h2>
            <p class="text-muted small"><?= $e($t('admin.quotes.lines_hint')) ?></p>

            <div class="js-quote-lines" data-next-index="<?= count($lines) ?>">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th style="min-width: 220px;"><?= $e($t('admin.quotes.line_description')) ?></th>
                                <th style="width: 110px;"><?= $e($t('admin.quotes.line_qty')) ?></th>
                                <th style="width: 110px;"><?= $e($t('admin.quotes.line_unit')) ?></th>
                                <th style="width: 140px;"><?= $e($t('admin.quotes.line_price')) ?></th>
                                <th class="text-end" style="width: 120px;"><?= $e($t('admin.quotes.line_total')) ?></th>
                                <th style="width: 44px;"></th>
                            </tr>
                        </thead>
                        <tbody class="js-quote-lines-body">
                        <?php foreach (array_values($lines) as $i => $line): ?>
                            <tr class="js-quote-line">
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="lines[<?= $i ?>][description]"
                                           value="<?= $e($line['description']) ?>" placeholder="<?= $e($t('admin.quotes.line_description_placeholder')) ?>">
                                </td>
                                <td>
                                    <input type="number" step="0.001" min="0" class="form-control form-control-sm"
                                           name="lines[<?= $i ?>][qty]" data-role="qty" value="<?= $e($num($line['qty'])) ?>">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="lines[<?= $i ?>][unit]"
                                           value="<?= $e($line['unit']) ?>" placeholder="<?= $e($t('admin.quotes.line_unit_placeholder')) ?>">
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                           name="lines[<?= $i ?>][unit_price]" data-role="price" value="<?= $e((string) $line['unit_price']) ?>">
                                </td>
                                <td class="text-end text-nowrap js-quote-line-total">—</td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger js-quote-remove-line"
                                            aria-label="<?= $e($t('admin.interventions.remove_material')) ?>">
                                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <template class="js-quote-line-template">
                    <tr class="js-quote-line">
                        <td>
                            <input type="text" class="form-control form-control-sm" name="lines[__INDEX__][description]"
                                   placeholder="<?= $e($t('admin.quotes.line_description_placeholder')) ?>">
                        </td>
                        <td>
                            <input type="number" step="0.001" min="0" class="form-control form-control-sm"
                                   name="lines[__INDEX__][qty]" data-role="qty" value="1">
                        </td>
                        <td>
                            <input type="text" class="form-control form-control-sm" name="lines[__INDEX__][unit]"
                                   placeholder="<?= $e($t('admin.quotes.line_unit_placeholder')) ?>">
                        </td>
                        <td>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                   name="lines[__INDEX__][unit_price]" data-role="price">
                        </td>
                        <td class="text-end text-nowrap js-quote-line-total">—</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger js-quote-remove-line"
                                    aria-label="<?= $e($t('admin.interventions.remove_material')) ?>">
                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                            </button>
                        </td>
                    </tr>
                </template>

                <button type="button" class="btn btn-outline-success btn-sm js-quote-add-line">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= $e($t('admin.quotes.line_add')) ?>
                </button>

                <div class="d-flex flex-column align-items-end gap-1 mt-3">
                    <div><?= $e($t('admin.quotes.subtotal')) ?>: <span class="fw-semibold js-quote-subtotal">—</span></div>
                    <div><?= $e($t('admin.quotes.vat_amount')) ?>: <span class="fw-semibold js-quote-vat-amount">—</span></div>
                    <div class="fs-5"><?= $e($t('admin.quotes.total')) ?>: <span class="fw-bold js-quote-total">—</span></div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.quotes.notes')) ?></label>
                <textarea class="form-control" name="notes" rows="3"
                          placeholder="<?= $e($t('admin.quotes.notes_placeholder')) ?>"><?= $e($value('notes')) ?></textarea>
            </div>

            <div class="alert alert-danger d-none js-crud-error" role="alert"></div>

            <div class="d-flex gap-2 pt-3 border-top">
                <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/quotes')) ?>"><?= $e($t('common.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
