<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed>|null $order null = create, row = edit */
/** @var array<int,array<string,mixed>> $lines Existing line items (edit only) */
/** @var array<int,array<string,mixed>> $suppliers */
/** @var array<int,array<string,mixed>> $projects */
/** @var array<int,array<string,mixed>> $locations */
/** @var array<int,array<string,mixed>> $items */
/** @var array<int,string> $statuses */
/** @var string $suggestedNumber */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$isEdit    = $order !== null;
$pageTitle = $isEdit ? $t('admin.purchase_orders.edit') : $t('admin.purchase_orders.new');
$value     = static fn (string $key): string => (string) ($order[$key] ?? '');
$num       = static fn ($v): string => rtrim(rtrim((string) $v, '0'), '.');

// The item <select> markup for a line, shared by existing rows and the JS template.
$itemOptions = static function (int $selected) use ($items, $e, $t): string {
    $html = '<option value="">' . $e($t('admin.purchase_orders.line_item_free')) . '</option>';
    foreach ($items as $it) {
        $sel  = $selected === (int) $it['id'] ? ' selected' : '';
        $html .= '<option value="' . $e((string) $it['id']) . '" data-unit="' . $e((string) $it['unit'])
            . '" data-name="' . $e((string) $it['name']) . '"' . $sel . '>' . $e((string) $it['name']) . '</option>';
    }
    return $html;
};
?>
<?= View::render('partials/page_head', [
    'title'    => $pageTitle,
    'subtitle' => $isEdit ? $order['number'] . ' — ' . $order['title'] : $t('admin.purchase_orders.subtitle'),
    'actions'  => View::render('partials/back_button', ['href' => '/admin/purchase-orders', 'label' => $t('admin.purchase_orders.back_to_list')], null),
], null) ?>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.purchase_orders.title'), '/admin/purchase-orders'],
    [$isEdit ? (string) $order['number'] : $t('admin.purchase_orders.new'), null],
]], null) ?>

<div class="card">
    <div class="card-body">
        <form class="js-crud-form"
              data-base-url="<?= $e(Url::to('/admin/purchase-orders')) ?>"
              data-redirect="<?= $e(Url::to('/admin/purchase-orders')) ?>">
            <input type="hidden" name="id" value="<?= $e($isEdit ? (string) $order['id'] : '') ?>">

            <h2 class="app-form-section"><?= $e($t('admin.purchase_orders.section_main')) ?></h2>
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.purchase_orders.supplier')) ?></label>
                    <select class="form-select" name="supplier_id" required>
                        <option value="">—</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $e((string) $s['id']) ?>"
                                    <?= $isEdit && (int) $order['supplier_id'] === (int) $s['id'] ? 'selected' : '' ?>>
                                <?= $e($s['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.purchase_orders.project_optional')) ?></label>
                    <select class="form-select" name="project_id">
                        <option value="">—</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $e((string) $p['id']) ?>"
                                    <?= $isEdit && (int) ($order['project_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>>
                                <?= $e($p['name']) ?><?php if (!empty($p['client_name'])): ?> — <?= $e($p['client_name']) ?><?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.purchase_orders.delivery_location')) ?></label>
                    <select class="form-select" name="location_id" required>
                        <?php foreach ($locations as $loc): ?>
                            <option value="<?= $e((string) $loc['id']) ?>"
                                    <?= ($isEdit ? (int) $order['location_id'] : 1) === (int) $loc['id'] ? 'selected' : '' ?>>
                                <?= $e($loc['name']) ?> — <?= $e(Lang::label('stock_location_kinds', (string) $loc['kind'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.purchase_orders.field_title')) ?></label>
                    <input type="text" class="form-control" name="title" value="<?= $e($value('title')) ?>"
                           placeholder="<?= $e($t('admin.purchase_orders.title_placeholder')) ?>" required>
                </div>
            </div>

            <div class="row">
                <div class="col-6 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.purchase_orders.number')) ?></label>
                    <input type="text" class="form-control" name="number"
                           value="<?= $e($isEdit ? (string) $order['number'] : $suggestedNumber) ?>" required>
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.purchase_orders.date')) ?></label>
                    <input type="date" class="form-control" name="order_date"
                           value="<?= $e($isEdit ? (string) $order['order_date'] : date('Y-m-d')) ?>" required>
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.purchase_orders.expected_date')) ?></label>
                    <input type="date" class="form-control" name="expected_date" value="<?= $e($value('expected_date')) ?>">
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.purchase_orders.status')) ?></label>
                    <select class="form-select" name="status">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $e($s) ?>"
                                    <?= ($isEdit ? $order['status'] : 'draft') === $s ? 'selected' : '' ?>>
                                <?= $e(Lang::label('po_status', $s)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-6 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.purchase_orders.vat_rate')) ?></label>
                    <input type="number" step="0.01" min="0" max="100" class="form-control js-po-vat" name="vat_rate"
                           value="<?= $e($isEdit ? $num($order['vat_rate']) : '22') ?>">
                </div>
            </div>

            <h2 class="app-form-section"><?= $e($t('admin.purchase_orders.section_lines')) ?></h2>
            <p class="text-muted small"><?= $e($t('admin.purchase_orders.lines_hint')) ?></p>

            <div class="js-po-lines" data-next-index="<?= count($lines) ?>">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th style="min-width: 160px;"><?= $e($t('admin.purchase_orders.line_item')) ?></th>
                                <th style="min-width: 200px;"><?= $e($t('admin.purchase_orders.line_description')) ?></th>
                                <th style="width: 100px;"><?= $e($t('admin.purchase_orders.line_qty')) ?></th>
                                <th style="width: 90px;"><?= $e($t('admin.purchase_orders.line_unit')) ?></th>
                                <th style="width: 130px;"><?= $e($t('admin.purchase_orders.line_price')) ?></th>
                                <th class="text-end" style="width: 120px;"><?= $e($t('admin.purchase_orders.line_total')) ?></th>
                                <th style="width: 44px;"></th>
                            </tr>
                        </thead>
                        <tbody class="js-po-lines-body">
                        <?php foreach (array_values($lines) as $i => $line): ?>
                            <tr class="js-po-line">
                                <td>
                                    <select class="form-select form-select-sm js-po-item" name="lines[<?= $i ?>][item_id]">
                                        <?= $itemOptions((int) ($line['item_id'] ?? 0)) ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="lines[<?= $i ?>][description]"
                                           value="<?= $e($line['description']) ?>" placeholder="<?= $e($t('admin.purchase_orders.line_description_placeholder')) ?>">
                                </td>
                                <td>
                                    <input type="number" step="0.001" min="0" class="form-control form-control-sm"
                                           name="lines[<?= $i ?>][qty]" data-role="qty" value="<?= $e($num($line['qty'])) ?>">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" name="lines[<?= $i ?>][unit]"
                                           value="<?= $e($line['unit']) ?>" placeholder="<?= $e($t('admin.purchase_orders.line_unit_placeholder')) ?>">
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                           name="lines[<?= $i ?>][unit_price]" data-role="price" value="<?= $e((string) $line['unit_price']) ?>">
                                </td>
                                <td class="text-end text-nowrap js-po-line-total">—</td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-outline-danger js-po-remove-line"
                                            aria-label="<?= $e($t('admin.purchase_orders.line_remove')) ?>">
                                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <template class="js-po-line-template">
                    <tr class="js-po-line">
                        <td>
                            <select class="form-select form-select-sm js-po-item" name="lines[__INDEX__][item_id]">
                                <?= $itemOptions(0) ?>
                            </select>
                        </td>
                        <td>
                            <input type="text" class="form-control form-control-sm" name="lines[__INDEX__][description]"
                                   placeholder="<?= $e($t('admin.purchase_orders.line_description_placeholder')) ?>">
                        </td>
                        <td>
                            <input type="number" step="0.001" min="0" class="form-control form-control-sm"
                                   name="lines[__INDEX__][qty]" data-role="qty" value="1">
                        </td>
                        <td>
                            <input type="text" class="form-control form-control-sm" name="lines[__INDEX__][unit]"
                                   placeholder="<?= $e($t('admin.purchase_orders.line_unit_placeholder')) ?>">
                        </td>
                        <td>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                   name="lines[__INDEX__][unit_price]" data-role="price">
                        </td>
                        <td class="text-end text-nowrap js-po-line-total">—</td>
                        <td class="text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger js-po-remove-line"
                                    aria-label="<?= $e($t('admin.purchase_orders.line_remove')) ?>">
                                <i class="bi bi-x-lg" aria-hidden="true"></i>
                            </button>
                        </td>
                    </tr>
                </template>

                <button type="button" class="btn btn-outline-success btn-sm js-po-add-line">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= $e($t('admin.purchase_orders.line_add')) ?>
                </button>

                <div class="row justify-content-end mt-3">
                    <div class="col-12 col-md-5 col-lg-4">
                        <div class="app-rail-card">
                            <div class="app-rail-title"><?= $e($t('admin.purchase_orders.summary_title')) ?></div>
                            <dl class="app-dl">
                                <div class="app-dl-row">
                                    <dt><?= $e($t('admin.purchase_orders.subtotal')) ?></dt>
                                    <dd class="js-po-subtotal">—</dd>
                                </div>
                                <div class="app-dl-row">
                                    <dt><?= $e($t('admin.purchase_orders.vat_amount')) ?></dt>
                                    <dd class="js-po-vat-amount">—</dd>
                                </div>
                                <div class="app-dl-row">
                                    <dt class="fs-6"><?= $e($t('admin.purchase_orders.total')) ?></dt>
                                    <dd class="fs-5 js-po-total">—</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.purchase_orders.notes')) ?></label>
                <textarea class="form-control" name="notes" rows="3"
                          placeholder="<?= $e($t('admin.purchase_orders.notes_placeholder')) ?>"><?= $e($value('notes')) ?></textarea>
            </div>

            <div class="alert alert-danger d-none js-crud-error" role="alert"></div>

            <div class="d-flex gap-2 pt-3 border-top">
                <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/purchase-orders')) ?>"><?= $e($t('common.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
