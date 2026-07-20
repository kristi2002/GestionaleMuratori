<?php
use App\Support\Lang;
use App\Support\Url;
use App\Support\View;

/** @var array<string,mixed> $company */
/** @var bool $isComplete */
/** @var array<int,string> $regimi */

$e = static fn (?string $v): string => View::e($v);
$t = static fn (string $key): string => Lang::get($key);
$v = static fn (string $key): string => (string) ($company[$key] ?? '');
?>
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div>
        <h1 class="h4 mb-1"><?= $e($t('admin.company.title')) ?></h1>
        <p class="text-muted mb-0"><?= $e($t('admin.company.subtitle')) ?></p>
    </div>
    <?= View::render('partials/back_button', ['href' => '/admin'], null) ?>
</div>

<?php if (!$isComplete): ?>
    <div class="alert alert-warning" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i><?= $e($t('admin.company.incomplete')) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form class="js-crud-form"
              data-base-url="<?= $e(Url::to('/admin/company')) ?>"
              data-redirect="<?= $e(Url::to('/admin/company')) ?>">

            <div class="mb-3">
                <label class="form-label"><?= $e($t('admin.company.denominazione')) ?></label>
                <input type="text" class="form-control" name="denominazione" value="<?= $e($v('denominazione')) ?>" required>
            </div>

            <div class="row">
                <div class="col-12 col-md-4 mb-3">
                    <label class="form-label"><?= $e($t('admin.clients.partita_iva')) ?></label>
                    <input type="text" class="form-control" name="partita_iva" maxlength="11" value="<?= $e($v('partita_iva')) ?>">
                </div>
                <div class="col-12 col-md-4 mb-3">
                    <label class="form-label"><?= $e($t('admin.clients.codice_fiscale')) ?></label>
                    <input type="text" class="form-control text-uppercase" name="codice_fiscale" maxlength="16" value="<?= $e($v('codice_fiscale')) ?>">
                </div>
                <div class="col-12 col-md-4 mb-3">
                    <label class="form-label"><?= $e($t('admin.company.regime_fiscale')) ?></label>
                    <?php $reg = $v('regime_fiscale') ?: 'RF01'; ?>
                    <select class="form-select" name="regime_fiscale">
                        <?php foreach ($regimi as $code): ?>
                            <option value="<?= $e($code) ?>" <?= $reg === $code ? 'selected' : '' ?>><?= $e($code) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text"><?= $e($t('admin.company.regime_hint')) ?></div>
                </div>
            </div>

            <fieldset class="border rounded p-3 mb-3">
                <legend class="float-none w-auto px-2 fs-6 text-muted mb-0"><?= $e($t('admin.company.address_section')) ?></legend>
                <div class="row">
                    <div class="col-12 col-md-8 mb-3">
                        <label class="form-label"><?= $e($t('admin.clients.address')) ?></label>
                        <input type="text" class="form-control" name="indirizzo" value="<?= $e($v('indirizzo')) ?>">
                    </div>
                    <div class="col-12 col-md-4 mb-3">
                        <label class="form-label"><?= $e($t('admin.company.numero_civico')) ?></label>
                        <input type="text" class="form-control" name="numero_civico" maxlength="20" value="<?= $e($v('numero_civico')) ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 col-md-3 mb-3">
                        <label class="form-label"><?= $e($t('admin.clients.cap')) ?></label>
                        <input type="text" class="form-control" name="cap" maxlength="10" value="<?= $e($v('cap')) ?>">
                    </div>
                    <div class="col-12 col-md-5 mb-3">
                        <label class="form-label"><?= $e($t('admin.clients.comune')) ?></label>
                        <input type="text" class="form-control" name="comune" maxlength="120" value="<?= $e($v('comune')) ?>">
                    </div>
                    <div class="col-6 col-md-2 mb-3">
                        <label class="form-label"><?= $e($t('admin.clients.provincia')) ?></label>
                        <input type="text" class="form-control text-uppercase" name="provincia" maxlength="2" value="<?= $e($v('provincia')) ?>">
                    </div>
                    <div class="col-6 col-md-2 mb-3">
                        <label class="form-label"><?= $e($t('admin.clients.nazione')) ?></label>
                        <input type="text" class="form-control text-uppercase" name="nazione" maxlength="2" value="<?= $e($v('nazione') ?: 'IT') ?>">
                    </div>
                </div>
            </fieldset>

            <fieldset class="border rounded p-3 mb-3">
                <legend class="float-none w-auto px-2 fs-6 text-muted mb-0"><?= $e($t('admin.company.contact_section')) ?></legend>
                <div class="row">
                    <div class="col-12 col-md-4 mb-3">
                        <label class="form-label"><?= $e($t('admin.clients.phone')) ?></label>
                        <input type="text" class="form-control" name="telefono" value="<?= $e($v('telefono')) ?>">
                    </div>
                    <div class="col-12 col-md-4 mb-3">
                        <label class="form-label"><?= $e($t('admin.clients.email')) ?></label>
                        <input type="email" class="form-control" name="email" value="<?= $e($v('email')) ?>">
                    </div>
                    <div class="col-12 col-md-4 mb-3">
                        <label class="form-label"><?= $e($t('admin.clients.pec')) ?></label>
                        <input type="email" class="form-control" name="pec" value="<?= $e($v('pec')) ?>">
                    </div>
                </div>
                <div class="mb-1">
                    <label class="form-label"><?= $e($t('admin.company.iban')) ?></label>
                    <input type="text" class="form-control text-uppercase" name="iban" maxlength="34" value="<?= $e($v('iban')) ?>">
                </div>
            </fieldset>

            <fieldset class="border rounded p-3 mb-3">
                <legend class="float-none w-auto px-2 fs-6 text-muted mb-0"><?= $e($t('admin.company.rea_section')) ?></legend>
                <div class="row">
                    <div class="col-6 col-md-2 mb-3">
                        <label class="form-label"><?= $e($t('admin.company.rea_ufficio')) ?></label>
                        <input type="text" class="form-control text-uppercase" name="rea_ufficio" maxlength="2" value="<?= $e($v('rea_ufficio')) ?>">
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <label class="form-label"><?= $e($t('admin.company.rea_numero')) ?></label>
                        <input type="text" class="form-control" name="rea_numero" maxlength="20" value="<?= $e($v('rea_numero')) ?>">
                    </div>
                    <div class="col-12 col-md-3 mb-3">
                        <label class="form-label"><?= $e($t('admin.company.capitale_sociale')) ?></label>
                        <input type="number" step="0.01" min="0" class="form-control" name="capitale_sociale" value="<?= $e($v('capitale_sociale')) ?>">
                    </div>
                    <div class="col-6 col-md-2 mb-3">
                        <label class="form-label"><?= $e($t('admin.company.socio_unico')) ?></label>
                        <?php $su = $v('socio_unico'); ?>
                        <select class="form-select" name="socio_unico">
                            <option value=""></option>
                            <option value="SM" <?= $su === 'SM' ? 'selected' : '' ?>><?= $e($t('admin.company.socio_sm')) ?></option>
                            <option value="SU" <?= $su === 'SU' ? 'selected' : '' ?>><?= $e($t('admin.company.socio_su')) ?></option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2 mb-3">
                        <label class="form-label"><?= $e($t('admin.company.stato_liquidazione')) ?></label>
                        <?php $liq = $v('stato_liquidazione') ?: 'LN'; ?>
                        <select class="form-select" name="stato_liquidazione">
                            <option value="LN" <?= $liq === 'LN' ? 'selected' : '' ?>><?= $e($t('admin.company.liq_ln')) ?></option>
                            <option value="LS" <?= $liq === 'LS' ? 'selected' : '' ?>><?= $e($t('admin.company.liq_ls')) ?></option>
                        </select>
                    </div>
                </div>
                <p class="text-muted small mb-0"><?= $e($t('admin.company.rea_hint')) ?></p>
            </fieldset>

            <div class="alert alert-danger d-none js-crud-error" role="alert"></div>

            <div class="d-flex gap-2 pt-3 border-top">
                <button type="submit" class="btn btn-success"><?= $e($t('common.save')) ?></button>
                <a class="btn btn-outline-secondary" href="<?= $e(Url::to('/admin')) ?>"><?= $e($t('common.cancel')) ?></a>
            </div>
        </form>
    </div>
</div>
