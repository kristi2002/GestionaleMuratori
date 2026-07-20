<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed>|null $invoice null = create, row = edit */
/** @var array<int,array<string,mixed>> $lines fiscal line items (empty for new/legacy) */
/** @var array<int,array<string,mixed>> $projects */
/** @var array<int,string> $statuses */
/** @var string $suggestedNumber */
/** @var array<int,string> $docTypes */
/** @var array<int,string> $nature */
/** @var array<int,string> $paymentMethods */
/** @var array<int,string> $ritenutaTipi */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);

$isEdit    = $invoice !== null;
$pageTitle = $isEdit ? $t('admin.invoices.edit') : $t('admin.invoices.new');
$value     = static fn (string $key): string => (string) ($invoice[$key] ?? '');
$num       = static fn ($v): string => rtrim(rtrim((string) $v, '0'), '.');
$vatRates  = ['22', '10', '5', '4', '0'];

// One blank line pre-filled for a new invoice so the editor is ready to type in.
$editorLines = $lines;
if ($editorLines === []) {
    $editorLines = [['description' => '', 'qty' => '1', 'unit' => '', 'unit_price' => '', 'vat_rate' => '22', 'natura' => '']];
}
?>
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($pageTitle) ?></h1>
        <p class="text-muted mb-0"><?= $e($isEdit ? $invoice['number'] : $t('admin.invoices.subtitle')) ?></p>
    </div>
    <?= View::render('partials/back_button', ['href' => '/admin/invoices', 'label' => $t('admin.invoices.back_to_list')], null) ?>
</div>

<?= View::render('partials/breadcrumb', ['items' => [
    [$t('nav.dashboard'), '/admin'],
    [$t('admin.invoices.title'), '/admin/invoices'],
    [$isEdit ? (string) $invoice['number'] : $t('admin.invoices.new'), null],
]], null) ?>

<div class="card">
    <div class="card-body">
        <form class="js-crud-form"
              data-base-url="<?= $e(Url::to('/admin/invoices')) ?>"
              data-redirect="<?= $e(Url::to('/admin/invoices')) ?>">
            <input type="hidden" name="id" value="<?= $e($isEdit ? (string) $invoice['id'] : '') ?>">

            <h2 class="app-form-section"><?= $e($t('admin.invoices.section_main')) ?></h2>
            <div class="row">
                <div class="col-12 col-md-6 mb-3">
                    <label class="form-label"><?= $e($t('admin.interventions.project')) ?></label>
                    <select class="form-select" name="project_id" required>
                        <option value="">—</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $e((string) $p['id']) ?>"
                                    <?= $isEdit && (int) $invoice['project_id'] === (int) $p['id'] ? 'selected' : '' ?>>
                                <?= $e($p['name']) ?> (<?= $e($p['client_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.projects.invoice_number')) ?></label>
                    <input type="text" class="form-control" name="number"
                           value="<?= $e($isEdit ? (string) $invoice['number'] : $suggestedNumber) ?>" required>
                </div>
                <div class="col-6 col-md-3 mb-3">
                    <label class="form-label"><?= $e($t('admin.invoices.document_type')) ?></label>
                    <select class="form-select" name="document_type">
                        <?php $dt = $isEdit ? (string) ($invoice['document_type'] ?? 'TD01') : 'TD01'; ?>
                        <?php foreach ($docTypes as $code): ?>
                            <option value="<?= $e($code) ?>" <?= $dt === $code ? 'selected' : '' ?>>
                                <?= $e($code) ?> — <?= $e($t('invoice_doc_types.' . $code)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-6 col-md-4 mb-3">
                    <label class="form-label"><?= $e($t('admin.projects.invoice_date')) ?></label>
                    <input type="date" class="form-control" name="issue_date"
                           value="<?= $e($isEdit ? (string) $invoice['issue_date'] : date('Y-m-d')) ?>" required>
                </div>
                <div class="col-6 col-md-4 mb-3">
                    <label class="form-label"><?= $e($t('admin.projects.invoice_status')) ?></label>
                    <select class="form-select" name="status">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?= $e($s) ?>" <?= ($isEdit ? $invoice['status'] : 'issued') === $s ? 'selected' : '' ?>>
                                <?= $e(Lang::label('invoice_status', $s)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2 mb-3">
                    <label class="form-label"><?= $e($t('admin.projects.cig')) ?></label>
                    <input type="text" class="form-control text-uppercase" name="cig" maxlength="10" value="<?= $e($value('cig')) ?>">
                </div>
                <div class="col-6 col-md-2 mb-3">
                    <label class="form-label"><?= $e($t('admin.projects.cup')) ?></label>
                    <input type="text" class="form-control text-uppercase" name="cup" maxlength="15" value="<?= $e($value('cup')) ?>">
                </div>
            </div>

            <h2 class="app-form-section"><?= $e($t('admin.invoices.section_lines')) ?></h2>
            <p class="text-muted small"><?= $e($t('admin.invoices.lines_hint')) ?></p>

            <div class="js-invoice-lines" data-next-index="<?= count($editorLines) ?>">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th style="min-width: 200px;"><?= $e($t('admin.quotes.line_description')) ?></th>
                                <th style="width: 90px;"><?= $e($t('admin.quotes.line_qty')) ?></th>
                                <th style="width: 80px;"><?= $e($t('admin.quotes.line_unit')) ?></th>
                                <th style="width: 120px;"><?= $e($t('admin.quotes.line_price')) ?></th>
                                <th style="width: 90px;"><?= $e($t('admin.invoices.line_vat')) ?></th>
                                <th style="width: 110px;"><?= $e($t('admin.invoices.line_natura')) ?></th>
                                <th class="text-end" style="width: 110px;"><?= $e($t('admin.quotes.line_total')) ?></th>
                                <th style="width: 40px;"></th>
                            </tr>
                        </thead>
                        <tbody class="js-invoice-lines-body">
                        <?php foreach (array_values($editorLines) as $i => $line): ?>
                            <tr class="js-invoice-line">
                                <td><input type="text" class="form-control form-control-sm" name="lines[<?= $i ?>][description]" value="<?= $e((string) $line['description']) ?>"></td>
                                <td><input type="number" step="0.001" min="0" class="form-control form-control-sm" name="lines[<?= $i ?>][qty]" data-role="qty" value="<?= $e($num($line['qty'])) ?>"></td>
                                <td><input type="text" class="form-control form-control-sm" name="lines[<?= $i ?>][unit]" value="<?= $e((string) ($line['unit'] ?? '')) ?>"></td>
                                <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm" name="lines[<?= $i ?>][unit_price]" data-role="price" value="<?= $e($line['unit_price'] !== '' ? $num($line['unit_price']) : '') ?>"></td>
                                <td>
                                    <select class="form-select form-select-sm" name="lines[<?= $i ?>][vat_rate]" data-role="vat">
                                        <?php foreach ($vatRates as $r): ?>
                                            <option value="<?= $e($r) ?>" <?= (string) ($line['vat_rate'] ?? '22') === $r || $num($line['vat_rate'] ?? '22') === $r ? 'selected' : '' ?>><?= $e($r) ?>%</option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <select class="form-select form-select-sm" name="lines[<?= $i ?>][natura]" data-role="natura">
                                        <option value="">—</option>
                                        <?php foreach ($nature as $n): ?>
                                            <option value="<?= $e($n) ?>" <?= (string) ($line['natura'] ?? '') === $n ? 'selected' : '' ?>><?= $e($n) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="text-end text-nowrap js-invoice-line-total">—</td>
                                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger js-invoice-remove-line" aria-label="×"><i class="bi bi-x-lg"></i></button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <template class="js-invoice-line-template">
                    <tr class="js-invoice-line">
                        <td><input type="text" class="form-control form-control-sm" name="lines[__INDEX__][description]"></td>
                        <td><input type="number" step="0.001" min="0" class="form-control form-control-sm" name="lines[__INDEX__][qty]" data-role="qty" value="1"></td>
                        <td><input type="text" class="form-control form-control-sm" name="lines[__INDEX__][unit]"></td>
                        <td><input type="number" step="0.0001" min="0" class="form-control form-control-sm" name="lines[__INDEX__][unit_price]" data-role="price"></td>
                        <td>
                            <select class="form-select form-select-sm" name="lines[__INDEX__][vat_rate]" data-role="vat">
                                <?php foreach ($vatRates as $r): ?><option value="<?= $e($r) ?>" <?= $r === '22' ? 'selected' : '' ?>><?= $e($r) ?>%</option><?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <select class="form-select form-select-sm" name="lines[__INDEX__][natura]" data-role="natura">
                                <option value="">—</option>
                                <?php foreach ($nature as $n): ?><option value="<?= $e($n) ?>"><?= $e($n) ?></option><?php endforeach; ?>
                            </select>
                        </td>
                        <td class="text-end text-nowrap js-invoice-line-total">—</td>
                        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger js-invoice-remove-line" aria-label="×"><i class="bi bi-x-lg"></i></button></td>
                    </tr>
                </template>

                <button type="button" class="btn btn-outline-success btn-sm js-invoice-add-line">
                    <i class="bi bi-plus-lg"></i> <?= $e($t('admin.invoices.line_add')) ?>
                </button>
                <p class="form-text mt-1"><?= $e($t('admin.invoices.natura_hint')) ?></p>
            </div>

            <div class="row mt-3">
                <div class="col-12 col-lg-7">
                    <h2 class="app-form-section"><?= $e($t('admin.invoices.section_fiscal')) ?></h2>
                    <div class="row">
                        <div class="col-6 col-md-3 mb-3">
                            <label class="form-label"><?= $e($t('admin.invoices.bollo')) ?></label>
                            <input type="number" step="0.01" min="0" class="form-control js-invoice-bollo" name="bollo" value="<?= $e($num($value('bollo'))) ?>">
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <label class="form-label"><?= $e($t('admin.invoices.ritenuta_rate')) ?></label>
                            <input type="number" step="0.01" min="0" max="100" class="form-control js-invoice-ritenuta-rate" name="ritenuta_rate" value="<?= $e($num($value('ritenuta_rate'))) ?>">
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <label class="form-label"><?= $e($t('admin.invoices.ritenuta_tipo')) ?></label>
                            <select class="form-select" name="ritenuta_tipo">
                                <option value="">—</option>
                                <?php foreach ($ritenutaTipi as $rt): ?>
                                    <option value="<?= $e($rt) ?>" <?= $value('ritenuta_tipo') === $rt ? 'selected' : '' ?>><?= $e($rt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-3 mb-3">
                            <label class="form-label"><?= $e($t('admin.invoices.ritenuta_causale')) ?></label>
                            <input type="text" class="form-control text-uppercase" name="ritenuta_causale" maxlength="2" value="<?= $e($value('ritenuta_causale')) ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12 col-md-4 mb-3">
                            <label class="form-label"><?= $e($t('admin.invoices.payment_method')) ?></label>
                            <select class="form-select" name="payment_method">
                                <?php $pm = $value('payment_method') ?: 'MP05'; ?>
                                <?php foreach ($paymentMethods as $mp): ?>
                                    <option value="<?= $e($mp) ?>" <?= $pm === $mp ? 'selected' : '' ?>><?= $e($mp) ?> — <?= $e($t('invoice_payment_methods.' . $mp)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-5 mb-3">
                            <label class="form-label"><?= $e($t('admin.invoices.payment_iban')) ?></label>
                            <input type="text" class="form-control text-uppercase" name="payment_iban" maxlength="34" value="<?= $e($value('payment_iban')) ?>">
                        </div>
                        <div class="col-12 col-md-3 mb-3">
                            <label class="form-label"><?= $e($t('admin.invoices.payment_due')) ?></label>
                            <input type="date" class="form-control" name="payment_due" value="<?= $e($value('payment_due')) ?>">
                        </div>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="split_payment" value="1" id="split_payment"
                               <?= (int) ($invoice['split_payment'] ?? 0) === 1 ? 'checked' : '' ?>>
                        <label class="form-check-label" for="split_payment"><?= $e($t('admin.invoices.split_payment')) ?></label>
                    </div>
                </div>

                <div class="col-12 col-lg-5">
                    <div class="app-rail-card">
                        <div class="app-rail-title"><?= $e($t('admin.invoices.summary_title')) ?></div>
                        <dl class="app-dl">
                            <div class="app-dl-row"><dt><?= $e($t('report.subtotal')) ?></dt><dd class="js-invoice-imponibile">—</dd></div>
                            <div class="app-dl-row"><dt><?= $e($t('report.vat')) ?></dt><dd class="js-invoice-imposta">—</dd></div>
                            <div class="app-dl-row"><dt class="fs-6"><?= $e($t('admin.invoices.total_document')) ?></dt><dd class="fs-6 js-invoice-total">—</dd></div>
                            <div class="app-dl-row"><dt><?= $e($t('admin.invoices.ritenuta')) ?></dt><dd class="js-invoice-ritenuta">—</dd></div>
                            <div class="app-dl-row"><dt class="fs-6"><?= $e($t('admin.invoices.net_to_pay')) ?></dt><dd class="fs-5 js-invoice-net">—</dd></div>
                        </dl>
                    </div>
                    <div class="mt-3">
                        <label class="form-label"><?= $e($t('admin.invoices.amount_fallback')) ?></label>
                        <input type="number" step="0.01" min="0" class="form-control" name="amount"
                               value="<?= $e($isEdit && empty($lines) ? $num($value('amount')) : '') ?>">
                        <div class="form-text"><?= $e($t('admin.invoices.amount_fallback_hint')) ?></div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.projects.invoice_note')) ?></label>
                <input type="text" class="form-control" name="note" value="<?= $e($value('note')) ?>">
            </div>

            <div class="alert alert-danger d-none js-crud-error" role="alert"></div>

            <div class="d-flex gap-2 pt-3 border-top">
                <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin/invoices')) ?>"><?= $e($t('common.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
