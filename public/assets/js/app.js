/* global jQuery */
(function ($) {
    'use strict';

    var BASE = document.body.getAttribute('data-base') || '';
    var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    // Tiny i18n bridge. The layout may inject a <script type="application/json"
    // id="gm-i18n"> dictionary (dotted key -> Italian string); GM.t(key, fallback)
    // returns the translation when present, otherwise the fallback literal. This
    // lets JS strings live in lang/it.php without a build step, while degrading
    // gracefully to the inline fallback if the dictionary is absent.
    var GM = (function () {
        var dict = {};
        try {
            var node = document.getElementById('gm-i18n');
            if (node) { dict = JSON.parse(node.textContent || '{}') || {}; }
        } catch (e) { dict = {}; }
        return {
            t: function (key, fallback) {
                return Object.prototype.hasOwnProperty.call(dict, key) ? dict[key] : fallback;
            }
        };
    })();
    window.GM = GM;

    // Every jQuery AJAX request carries the CSRF token; the server rejects POSTs
    // without it (checked globally in public/index.php). $.ajaxSetup also covers
    // the FormData uploads below, which go through $.ajax as well.
    $.ajaxSetup({ headers: { 'X-CSRF-Token': CSRF } });

    // Register the service worker (installable, offline-capable app shell). Scoped
    // to BASE so it works at a domain root or under a subdirectory. Best-effort.
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(BASE + '/sw.js', { scope: BASE + '/' }).catch(function () {
                // No SW (e.g. insecure origin) — the app still works fully online.
            });
        });
    }

    // Shared AJAX helper. Always flags the request as XHR so the server returns
    // JSON ({ ok, data?, error? }) instead of HTML.
    window.Api = {
        request: function (method, path, data) {
            return $.ajax({
                url: BASE + path,
                method: method,
                data: data,
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF }
            });
        },
        post: function (path, data) { return this.request('POST', path, data); },
        get: function (path, data) { return this.request('GET', path, data); }
    };

    // --- In-app dialogs -------------------------------------------------------
    // One reusable Bootstrap modal replacing the native window.alert/confirm
    // popups, so messages match the app's look. Callback-based since a modal
    // cannot block like the native dialogs: pass onConfirm/onCancel instead of
    // reading a return value. Falls back to the native dialogs if the Bootstrap
    // bundle failed to load (e.g. no CDN), keeping every action usable.
    var Dialog = (function () {
        var modal = null;
        var $el = null;
        var confirmed = false;
        var onConfirm = null;
        var onCancel = null;

        function ensure() {
            if ($el) {
                return true;
            }
            if (!window.bootstrap || !window.bootstrap.Modal) {
                return false;
            }
            $el = $(
                '<div class="modal fade app-dialog" tabindex="-1" aria-hidden="true">' +
                '<div class="modal-dialog modal-dialog-centered">' +
                '<div class="modal-content">' +
                '<div class="modal-header">' +
                '<h1 class="modal-title fs-6 js-dialog-title"></h1>' +
                '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Chiudi"></button>' +
                '</div>' +
                '<div class="modal-body js-dialog-message"></div>' +
                '<div class="modal-footer">' +
                '<button type="button" class="btn btn-outline-secondary js-dialog-cancel" data-bs-dismiss="modal">Annulla</button>' +
                '<button type="button" class="btn btn-success js-dialog-ok">OK</button>' +
                '</div>' +
                '</div></div></div>'
            ).appendTo('body');
            modal = new window.bootstrap.Modal($el[0]);

            $el.on('click', '.js-dialog-ok', function () {
                confirmed = true;
                modal.hide();
            });
            // Esc, backdrop, × and "Annulla" all land here with confirmed=false.
            $el.on('hidden.bs.modal', function () {
                var cb = confirmed ? onConfirm : onCancel;
                onConfirm = null;
                onCancel = null;
                if (cb) {
                    cb();
                }
            });
            return true;
        }

        function open(opts) {
            confirmed = false;
            onConfirm = opts.onConfirm || null;
            onCancel = opts.onCancel || null;
            $el.find('.js-dialog-title').text(opts.title);
            $el.find('.js-dialog-message').text(opts.message);
            $el.find('.js-dialog-cancel').toggleClass('d-none', !opts.cancel);
            $el.find('.js-dialog-ok')
                .attr('class', 'btn js-dialog-ok ' + (opts.okClass || 'btn-success'))
                .text(opts.okLabel || 'OK');
            modal.show();
        }

        return {
            alert: function (message, done) {
                if (!ensure()) {
                    window.alert(message);
                    if (done) {
                        done();
                    }
                    return;
                }
                open({ title: 'Errore', message: message, cancel: false, onConfirm: done, onCancel: done });
            },
            // opts: { title, okLabel, okClass, onConfirm, onCancel }
            confirm: function (message, opts) {
                opts = opts || {};
                if (!ensure()) {
                    if (window.confirm(message)) {
                        if (opts.onConfirm) {
                            opts.onConfirm();
                        }
                    } else if (opts.onCancel) {
                        opts.onCancel();
                    }
                    return;
                }
                open({
                    title: opts.title || 'Conferma',
                    message: message,
                    cancel: true,
                    okLabel: opts.okLabel || 'Conferma',
                    okClass: opts.okClass,
                    onConfirm: opts.onConfirm,
                    onCancel: opts.onCancel
                });
            }
        };
    })();
    window.AppDialog = Dialog;

    // --- Login form ---------------------------------------------------------
    $(function () {
        var $form = $('#login-form');
        if (!$form.length) {
            return;
        }
        var $error = $('#login-error');
        var $submit = $('#login-submit');

        $form.on('submit', function (e) {
            e.preventDefault();
            $error.addClass('d-none').text('');
            $submit.prop('disabled', true).text('Accesso…');

            Api.post('/login', {
                email: $('#email').val(),
                password: $('#password').val()
            }).done(function (res) {
                if (res && res.ok && res.data && res.data.redirect) {
                    window.location.href = res.data.redirect;
                } else {
                    showError((res && res.error) || 'Errore imprevisto.');
                }
            }).fail(function (xhr) {
                var msg = 'Errore di connessione.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                }
                showError(msg);
            });
        });

        function showError(msg) {
            $error.removeClass('d-none').text(msg);
            $submit.prop('disabled', false).text('Accedi');
        }
    });

    // --- Page loading feedback -----------------------------------------------
    // A light overlay spinner shown while the browser fetches the next page,
    // so slow navigations never look like a frozen screen.
    $(function () {
        var $overlay = $(
            '<div class="app-loading-overlay d-none" role="status" aria-live="polite">' +
            '<div class="spinner-border text-success" style="width:3rem;height:3rem;"></div>' +
            '</div>'
        ).appendTo('body');

        var hideTimer = null;
        function hideOverlay() {
            $overlay.addClass('d-none');
            if (hideTimer) { window.clearTimeout(hideTimer); hideTimer = null; }
        }

        // A click on a download / new-tab / in-page link fires beforeunload but
        // never actually unloads the document (the browser streams the file or
        // opens a new tab), so a plain overlay-on-beforeunload would spin forever.
        // Detect those clicks and suppress the overlay for them.
        var suppressOverlay = false;
        $(document).on('click', 'a[href]', function () {
            var $a = $(this);
            var href = $a.attr('href') || '';
            var target = ($a.attr('target') || '').toLowerCase();
            suppressOverlay = $a.is('[download]') || target === '_blank' ||
                href.charAt(0) === '#' ||
                /^(mailto:|tel:|javascript:)/i.test(href) ||
                /\/(pdf|excel|print)(\/|$|\?)|\/report\/|\/exports?\/|\.pdf($|\?)|\.xlsx?($|\?)/i.test(href);
        });

        window.addEventListener('beforeunload', function () {
            if (suppressOverlay) { suppressOverlay = false; return; }
            $overlay.removeClass('d-none');
            // Safety net: if the navigation never completes (download, blocked
            // popup, cancelled prompt) clear the overlay instead of spinning forever.
            hideTimer = window.setTimeout(hideOverlay, 4000);
        });

        // Coming back to the page (bfcache back/forward, or focus regained after a
        // download/print dialog) must always clear a lingering overlay.
        window.addEventListener('pageshow', hideOverlay);
        window.addEventListener('focus', hideOverlay);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) { hideOverlay(); }
        });
    });

    // Swap a button's content for a small spinner while an AJAX call runs.
    function buttonLoading($btn) {
        if (!$btn.data('original-html')) {
            $btn.data('original-html', $btn.html());
        }
        $btn.prop('disabled', true)
            .html('<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>' + $btn.text());
    }

    function buttonReset($btn) {
        $btn.prop('disabled', false);
        if ($btn.data('original-html')) {
            $btn.html($btn.data('original-html'));
        }
    }

    // --- Admin CRUD (clients / projects / warehouse) -------------------------
    // Generic create/edit modal + delete/toggle buttons driven by data-* attributes,
    // shared across all three admin resource pages so each view stays plain HTML.
    $(function () {
        function failMessage(xhr) {
            return (xhr.responseJSON && xhr.responseJSON.error) || 'Errore di connessione.';
        }

        $(document).on('submit', '.js-crud-form', function (e) {
            e.preventDefault();
            var $form = $(this);
            var confirmMsg = $form.data('confirm');
            if (confirmMsg) {
                Dialog.confirm(confirmMsg, { onConfirm: function () { submitCrudForm($form); } });
                return;
            }
            submitCrudForm($form);
        });

        function submitCrudForm($form) {
            var id = $form.find('[name="id"]').val();
            var url = id ? $form.data('base-url') + '/' + id : $form.data('base-url');
            var $error = $form.find('.js-crud-error');
            var $submit = $form.find('[type="submit"]');

            $error.addClass('d-none').text('');
            buttonLoading($submit);

            Api.post(url, $form.serialize()).done(function (res) {
                if (res && res.ok) {
                    // Standalone form pages set data-redirect (back to the list);
                    // modal/in-page forms keep the reload behaviour.
                    var redirect = $form.data('redirect');
                    if (redirect) {
                        window.location.href = redirect;
                    } else {
                        window.location.reload();
                    }
                } else {
                    $error.removeClass('d-none').text((res && res.error) || 'Errore imprevisto.');
                    buttonReset($submit);
                }
            }).fail(function (xhr) {
                $error.removeClass('d-none').text(failMessage(xhr));
                buttonReset($submit);
            });
        }

        // User form: the linked-client field only applies to 'client' accounts.
        $(document).on('change', '.js-user-role', function () {
            $('.js-user-client-field').toggleClass('d-none', $(this).val() !== 'client');
        });

        $(document).on('click', '.js-crud-new', function () {
            var $modal = $($(this).data('target-modal'));
            var $form = $modal.find('form');
            $form[0].reset();
            $form.find('[name="id"]').val('');
            $form.find('.js-crud-error').addClass('d-none');
            $modal.find('.modal-title').text($modal.data('title-create'));
        });

        $(document).on('click', '.js-crud-edit', function () {
            var record = $(this).data('record');
            var $modal = $($(this).data('target-modal'));
            var $form = $modal.find('form');
            $form[0].reset();
            $.each(record, function (key, val) {
                $form.find('[name="' + key + '"]').val(val === null ? '' : val);
            });
            $form.find('.js-crud-error').addClass('d-none');
            $modal.find('.modal-title').text($modal.data('title-edit'));
        });

        $(document).on('click', '.js-crud-delete', function () {
            var $btn = $(this);
            Dialog.confirm($btn.data('confirm') || 'Confermi?', {
                okLabel: 'Elimina',
                okClass: 'btn-danger',
                onConfirm: function () {
                    Api.post($btn.data('url'), {}).done(function (res) {
                        if (res && res.ok) {
                            var redirect = $btn.data('redirect');
                            if (redirect) {
                                window.location.href = redirect;
                            } else {
                                window.location.reload();
                            }
                        } else {
                            Dialog.alert((res && res.error) || 'Errore imprevisto.');
                        }
                    }).fail(function (xhr) {
                        Dialog.alert(failMessage(xhr));
                    });
                }
            });
        });

        // Generic confirm-then-POST button (e.g. quote → invoice conversion).
        // On success follows the redirect returned by the server, if any.
        $(document).on('click', '.js-post-action', function () {
            var $btn = $(this);
            var run = function () {
                Api.post($btn.data('url'), {}).done(function (res) {
                    if (res && res.ok) {
                        var redirect = (res.data && res.data.redirect) || $btn.data('redirect');
                        if (redirect) {
                            window.location.href = redirect;
                        } else {
                            window.location.reload();
                        }
                    } else {
                        Dialog.alert((res && res.error) || 'Errore imprevisto.');
                    }
                }).fail(function (xhr) {
                    Dialog.alert(failMessage(xhr));
                });
            };
            var message = $btn.data('confirm');
            if (message) {
                Dialog.confirm(message, {
                    okLabel: $btn.data('ok-label') || 'Conferma',
                    okClass: 'btn-success',
                    onConfirm: run
                });
            } else {
                run();
            }
        });

        $(document).on('click', '.js-toggle-active', function () {
            Api.post($(this).data('url'), {}).done(function (res) {
                if (res && res.ok) {
                    window.location.reload();
                } else {
                    Dialog.alert((res && res.error) || 'Errore imprevisto.');
                }
            }).fail(function (xhr) {
                Dialog.alert(failMessage(xhr));
            });
        });

        // --- Project attendance register (Registro Presenze Cantiere) ----------
        // Absence-by-default day boxes: a click saves the toggle for that
        // project + worker + date and flips the cell between green (Lavorato)
        // and gray (Assente) without reloading.
        $(document).on('click', '.js-att-day', function () {
            var $cell = $(this);
            if ($cell.prop('disabled')) {
                return;
            }
            $cell.prop('disabled', true);
            Api.post($cell.data('url'), {
                worker_id: $cell.data('worker'),
                date: $cell.data('date')
            }).done(function (res) {
                if (res && res.ok && res.data) {
                    $cell.toggleClass('st-absent', res.data.status === 'absent');
                } else {
                    Dialog.alert((res && res.error) || $cell.closest('[data-att-error]').data('att-error') || 'Errore imprevisto.');
                }
            }).fail(function (xhr) {
                Dialog.alert(failMessage(xhr));
            }).always(function () {
                $cell.prop('disabled', false);
            });
        });

        // --- Project attendance: worker selector + monthly calendar ------------
        // One calendar panel per assigned worker; clicking a worker card swaps
        // the visible panel. Assigning builds the card and its month grid in
        // place (leading/trailing offsets included); removing deletes both.
        // Neither reloads the page, and the dropdown stays in sync.
        function attFindPanel(workerId) {
            return $('.js-att-panel').filter(function () {
                return String($(this).data('worker')) === String(workerId);
            });
        }

        function attSelectWorker($item) {
            $('.js-att-worker').removeClass('active');
            $item.addClass('active');
            $('.js-att-panel').addClass('d-none');
            attFindPanel($item.data('worker')).removeClass('d-none');
        }

        function buildWorkerItem($reg, workerId, name) {
            var $item = $('<div class="app-att-worker-item js-att-worker" role="button" tabindex="0"></div>')
                .attr('data-worker', workerId);
            $('<span class="app-att-worker-name"></span>').attr('title', name).text(name).appendTo($item);
            $('<button type="button" class="app-att-remove js-att-remove"></button>')
                .attr({
                    'data-url': $reg.attr('data-remove-url-base') + '/' + workerId + '/remove',
                    'data-worker': workerId,
                    'data-name': name,
                    'data-confirm': $reg.attr('data-remove-confirm'),
                    'title': $reg.attr('data-remove-label'),
                    'aria-label': $reg.attr('data-remove-label')
                })
                .append('<i class="bi bi-x-lg" aria-hidden="true"></i>')
                .appendTo($item);
            return $item;
        }

        function buildCalendarPanel($reg, workerId, absences) {
            var days = parseInt($reg.attr('data-days'), 10);
            var lead = parseInt($reg.attr('data-lead'), 10);
            var trail = (7 - ((lead + days) % 7)) % 7;
            var prefix = $reg.attr('data-month-prefix');
            var today = $reg.attr('data-today');
            var weekdays = JSON.parse($reg.attr('data-weekdays'));
            var i;

            var $panel = $('<div class="js-att-panel d-none"></div>').attr('data-worker', workerId);
            var $head = $('<div class="app-att-weekdays" aria-hidden="true"></div>').appendTo($panel);
            weekdays.forEach(function (label) {
                $('<span></span>').text(label).appendTo($head);
            });

            var $grid = $('<div class="app-att-grid"></div>').appendTo($panel);
            for (i = 0; i < lead; i++) {
                $('<span class="app-att-day is-empty" aria-hidden="true"></span>').appendTo($grid);
            }
            for (i = 1; i <= days; i++) {
                var date = prefix + (i < 10 ? '0' + i : i);
                $('<button type="button" class="app-att-day js-att-day"></button>')
                    .toggleClass('st-absent', (absences || []).indexOf(date) !== -1)
                    .toggleClass('is-today', date === today)
                    .attr({
                        'data-url': $reg.attr('data-toggle-url'),
                        'data-worker': workerId,
                        'data-date': date
                    })
                    .text(i)
                    .appendTo($grid);
            }
            for (i = 0; i < trail; i++) {
                $('<span class="app-att-day is-empty" aria-hidden="true"></span>').appendTo($grid);
            }
            return $panel;
        }

        $(document).on('click', '.js-att-worker', function () {
            attSelectWorker($(this));
        });

        $(document).on('keydown', '.js-att-worker', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                attSelectWorker($(this));
            }
        });

        $(document).on('submit', '.js-att-assign', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $select = $form.find('.js-att-assign-select');
            var workerId = $select.val();
            if (!workerId) {
                return;
            }
            var $submit = $form.find('[type="submit"]');
            buttonLoading($submit);

            var $reg = $('.js-att-register');
            Api.post($form.data('url'), { worker_id: workerId, month: $reg.attr('data-month') }).done(function (res) {
                buttonReset($submit);
                if (res && res.ok && res.data) {
                    $('.js-att-empty').addClass('d-none');
                    $reg.removeClass('d-none');
                    var $item = buildWorkerItem($reg, res.data.id, res.data.name);
                    $('.js-att-workers').append($item);
                    $('.js-att-panels').append(buildCalendarPanel($reg, res.data.id, res.data.absences));
                    attSelectWorker($item);
                    $select.find('option[value="' + workerId + '"]').remove();
                    if (!$select.find('option').filter(function () { return this.value !== ''; }).length) {
                        $select.find('option[value=""]').text($form.data('none-label'));
                        $select.prop('disabled', true);
                        $submit.prop('disabled', true);
                    }
                    $select.val('');
                } else {
                    Dialog.alert((res && res.error) || 'Errore imprevisto.');
                }
            }).fail(function (xhr) {
                buttonReset($submit);
                Dialog.alert(failMessage(xhr));
            });
        });

        $(document).on('click', '.js-att-remove', function (e) {
            e.stopPropagation(); // do not also select the worker card being removed
            var $btn = $(this);
            Dialog.confirm($btn.data('confirm') || 'Confermi?', {
                okLabel: 'Rimuovi',
                okClass: 'btn-danger',
                onConfirm: function () { removeAttWorker($btn); }
            });
        });

        function removeAttWorker($btn) {
            $btn.prop('disabled', true);
            Api.post($btn.data('url'), {}).done(function (res) {
                if (res && res.ok) {
                    // Put the worker back into the assign dropdown, re-enabling it.
                    var $form = $('.js-att-assign');
                    var $select = $form.find('.js-att-assign-select');
                    if ($select.prop('disabled')) {
                        $select.prop('disabled', false);
                        $select.find('option[value=""]').text($form.data('placeholder-label'));
                        $form.find('[type="submit"]').prop('disabled', false);
                    }
                    $('<option></option>').val($btn.data('worker')).text($btn.data('name')).appendTo($select);

                    var $item = $btn.closest('.js-att-worker');
                    var wasActive = $item.hasClass('active');
                    attFindPanel($btn.data('worker')).remove();
                    $item.remove();

                    var $remaining = $('.js-att-worker');
                    if (!$remaining.length) {
                        $('.js-att-register').addClass('d-none');
                        $('.js-att-empty').removeClass('d-none');
                    } else if (wasActive) {
                        attSelectWorker($remaining.first());
                    }
                } else {
                    Dialog.alert((res && res.error) || 'Errore imprevisto.');
                    $btn.prop('disabled', false);
                }
            }).fail(function (xhr) {
                Dialog.alert(failMessage(xhr));
                $btn.prop('disabled', false);
            });
        }

        // --- Generic multipart upload form (project documents) -----------------
        // Posts the form as FormData to data-url and reloads on success, so file
        // inputs work where .js-crud-form (serialize-based) cannot.
        $(document).on('submit', '.js-upload-form', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $error = $form.find('.js-upload-error');
            var $submit = $form.find('[type="submit"]');

            $error.addClass('d-none').text('');
            buttonLoading($submit);

            $.ajax({
                url: BASE + $form.data('url'),
                method: 'POST',
                data: new FormData(this),
                processData: false,
                contentType: false,
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).done(function (res) {
                if (res && res.ok) {
                    window.location.reload();
                } else {
                    $error.removeClass('d-none').text((res && res.error) || 'Errore imprevisto.');
                    buttonReset($submit);
                }
            }).fail(function (xhr) {
                $error.removeClass('d-none').text(failMessage(xhr));
                buttonReset($submit);
            });
        });

        // --- Client profile: open a specific tab from a button outside the tab bar
        // (e.g. "Rendiconto" in the header jumps to the Fatture / Contratti tab).
        $(document).on('click', '.js-open-tab', function () {
            var $tab = $($(this).data('tab'));
            if ($tab.length) {
                $tab.trigger('click');
                $tab[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        // --- Warehouse: ledger reconciliation (§4.1) ------------------------------
        $(document).on('click', '.js-reconcile-btn', function () {
            var $btn = $(this);
            var $result = $('.js-reconcile-result');
            $btn.prop('disabled', true);

            Api.post($btn.data('url'), {}).done(function (res) {
                $btn.prop('disabled', false);
                if (res && res.ok) {
                    $result.removeClass('d-none alert-danger').addClass('alert-info').text(res.data.message);
                    if (res.data.changed) {
                        $('.js-qty-in-stock').text(res.data.after);
                    }
                } else {
                    $result.removeClass('d-none alert-info').addClass('alert-danger').text((res && res.error) || 'Errore imprevisto.');
                }
            }).fail(function (xhr) {
                $btn.prop('disabled', false);
                $result.removeClass('d-none alert-info').addClass('alert-danger').text(failMessage(xhr));
            });
        });

        // Sleek text tooltips on the icon-only action buttons.
        $('[data-bs-toggle="tooltip"]').each(function () {
            if (window.bootstrap && window.bootstrap.Tooltip) {
                window.bootstrap.Tooltip.getOrCreateInstance(this);
            }
        });

        // --- Interventions: inline status selector -----------------------------
        // Changing the value saves immediately; on failure (or a declined cancel
        // confirmation) the select reverts to the stored current status.
        $(document).on('change', '.js-status-select', function () {
            var $sel = $(this);
            var previous = $sel.data('current');
            var toStatus = $sel.val();
            if (toStatus === previous) {
                return;
            }
            // A data-confirm-<status> attribute on the select gates that target
            // (used for 'completed', which settles the material reservations).
            var confirmMsg = $sel.data('confirm-' + toStatus);
            if (confirmMsg) {
                Dialog.confirm(confirmMsg, {
                    onConfirm: function () { saveStatusSelect($sel, toStatus, previous); },
                    onCancel: function () { $sel.val(previous); }
                });
                return;
            }
            saveStatusSelect($sel, toStatus, previous);
        });

        function saveStatusSelect($sel, toStatus, previous) {
            $sel.prop('disabled', true);
            Api.post($sel.data('url'), { to_status: toStatus }).done(function (res) {
                if (res && res.ok) {
                    window.location.reload();
                } else {
                    Dialog.alert((res && res.error) || 'Errore imprevisto.');
                    $sel.val(previous).prop('disabled', false);
                }
            }).fail(function (xhr) {
                Dialog.alert(failMessage(xhr));
                $sel.val(previous).prop('disabled', false);
            });
        }

        // --- Interventions: status transition buttons -------------------------
        $(document).on('click', '.js-intervention-status', function () {
            var $btn = $(this);
            var confirmMsg = $btn.data('confirm');
            if (confirmMsg) {
                Dialog.confirm(confirmMsg, { onConfirm: function () { postInterventionStatus($btn); } });
                return;
            }
            postInterventionStatus($btn);
        });

        function postInterventionStatus($btn) {
            Api.post($btn.data('url'), { to_status: $btn.data('to-status') }).done(function (res) {
                if (res && res.ok) {
                    window.location.reload();
                } else {
                    Dialog.alert((res && res.error) || 'Errore imprevisto.');
                }
            }).fail(function (xhr) {
                Dialog.alert(failMessage(xhr));
            });
        }

        // --- Worker: photo upload (§8 offline-friendly) ---------------------------
        // Compresses client-side before upload; on a network failure (offline —
        // distinct from a server-side validation rejection) the compressed photo is
        // queued in localStorage and retried automatically on reconnect/page load.
        var PHOTO_QUEUE_KEY = 'gm_photo_queue_v1';

        function loadPhotoQueue() {
            try {
                return JSON.parse(window.localStorage.getItem(PHOTO_QUEUE_KEY) || '[]');
            } catch (e) {
                return [];
            }
        }

        function savePhotoQueue(queue) {
            try {
                window.localStorage.setItem(PHOTO_QUEUE_KEY, JSON.stringify(queue));
            } catch (e) {
                // Storage full or unavailable (e.g. private browsing) — best-effort only.
            }
        }

        function updateQueueBanner() {
            var $banner = $('.js-offline-queue-banner');
            if (!$banner.length) {
                return;
            }
            var count = loadPhotoQueue().length;
            if (count > 0) {
                $banner.removeClass('d-none').text(
                    count === 1 ? '1 foto in attesa di connessione.' : count + ' foto in attesa di connessione.'
                );
            } else {
                $banner.addClass('d-none').text('');
            }
        }

        function compressImageToDataUrl(file, maxDim, quality) {
            return new Promise(function (resolve, reject) {
                var reader = new FileReader();
                reader.onerror = function () { reject(new Error('read-failed')); };
                reader.onload = function () {
                    var img = new Image();
                    img.onerror = function () { reject(new Error('decode-failed')); };
                    img.onload = function () {
                        var scale = Math.min(1, maxDim / Math.max(img.width, img.height));
                        var w = Math.max(1, Math.round(img.width * scale));
                        var h = Math.max(1, Math.round(img.height * scale));
                        var canvas = document.createElement('canvas');
                        canvas.width = w;
                        canvas.height = h;
                        canvas.getContext('2d').drawImage(img, 0, 0, w, h);
                        resolve(canvas.toDataURL('image/jpeg', quality));
                    };
                    img.src = reader.result;
                };
                reader.readAsDataURL(file);
            });
        }

        function dataUrlToBlob(dataUrl) {
            var parts = dataUrl.split(',');
            var mime = parts[0].match(/:(.*?);/)[1];
            var binary = window.atob(parts[1]);
            var bytes = new Uint8Array(binary.length);
            for (var i = 0; i < binary.length; i++) {
                bytes[i] = binary.charCodeAt(i);
            }
            return new Blob([bytes], { type: mime });
        }

        function uploadPhotoBlob(url, type, blob) {
            var data = new FormData();
            data.append('photo', blob, 'photo.jpg');
            data.append('type', type);
            return $.ajax({
                url: BASE + url,
                method: 'POST',
                data: data,
                processData: false,
                contentType: false,
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
        }

        function queuePhotoForRetry(url, type, dataUrl) {
            var queue = loadPhotoQueue();
            queue.push({ id: Date.now() + '-' + Math.random().toString(36).slice(2), url: url, type: type, dataUrl: dataUrl });
            savePhotoQueue(queue);
            updateQueueBanner();
        }

        function flushPhotoQueue() {
            loadPhotoQueue().forEach(function (item) {
                uploadPhotoBlob(item.url, item.type, dataUrlToBlob(item.dataUrl)).always(function () {
                    // Drop on any definite server response (success or validation
                    // rejection) — only a .fail() with no response means "still
                    // offline," which leaves the item queued for the next retry.
                }).done(function () {
                    savePhotoQueue(loadPhotoQueue().filter(function (q) { return q.id !== item.id; }));
                    updateQueueBanner();
                }).fail(function (xhr) {
                    if (xhr.status > 0) {
                        savePhotoQueue(loadPhotoQueue().filter(function (q) { return q.id !== item.id; }));
                        updateQueueBanner();
                    }
                });
            });
        }

        function showPhotoMessage($error, message, isWarning) {
            $error.removeClass('d-none alert-danger alert-warning').addClass(isWarning ? 'alert-warning' : 'alert-danger').text(message);
        }

        $(document).on('submit', '.js-photo-upload-form', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $file = $form.find('input[type="file"]');
            var $error = $form.find('.js-photo-error');
            var $submit = $form.find('[type="submit"]');
            var url = $form.data('url');
            var type = $form.data('type');

            if (!$file[0].files.length) {
                return;
            }

            $error.addClass('d-none').text('');
            $submit.prop('disabled', true);

            compressImageToDataUrl($file[0].files[0], 1600, 0.8).then(function (dataUrl) {
                uploadPhotoBlob(url, type, dataUrlToBlob(dataUrl)).done(function (res) {
                    if (res && res.ok) {
                        window.location.reload();
                    } else {
                        showPhotoMessage($error, (res && res.error) || 'Errore imprevisto.', false);
                        $submit.prop('disabled', false);
                    }
                }).fail(function (xhr) {
                    if (xhr.status > 0) {
                        // The server responded (e.g. 401/500) — a real failure, not "offline".
                        showPhotoMessage($error, failMessage(xhr), false);
                        $submit.prop('disabled', false);
                        return;
                    }
                    queuePhotoForRetry(url, type, dataUrl);
                    showPhotoMessage($error, 'Sei offline: la foto è stata salvata sul dispositivo e verrà caricata automaticamente alla riconnessione.', true);
                    $submit.prop('disabled', false);
                    $form[0].reset();
                });
            }).catch(function () {
                showPhotoMessage($error, 'Impossibile elaborare l\'immagine selezionata.', false);
                $submit.prop('disabled', false);
            });
        });

        $(window).on('online', flushPhotoQueue);
        updateQueueBanner();
        flushPhotoQueue();

        // --- Worker: signature capture (canvas → PNG data URL) -------------------
        var canvas = document.getElementById('signature-pad');
        if (canvas) {
            var ctx = canvas.getContext('2d');
            var drawing = false;
            var hasStroke = false;

            function resizeCanvas() {
                var ratio = window.devicePixelRatio || 1;
                var rect = canvas.getBoundingClientRect();
                canvas.width = rect.width * ratio;
                canvas.height = rect.height * ratio;
                ctx.scale(ratio, ratio);
                ctx.lineWidth = 2;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#212529';
            }
            resizeCanvas();

            function pointFromEvent(e) {
                var rect = canvas.getBoundingClientRect();
                var point = e.touches ? e.touches[0] : e;
                return { x: point.clientX - rect.left, y: point.clientY - rect.top };
            }

            function start(e) {
                drawing = true;
                hasStroke = true;
                var p = pointFromEvent(e);
                ctx.beginPath();
                ctx.moveTo(p.x, p.y);
                e.preventDefault();
            }
            function move(e) {
                if (!drawing) {
                    return;
                }
                var p = pointFromEvent(e);
                ctx.lineTo(p.x, p.y);
                ctx.stroke();
                e.preventDefault();
            }
            function stop() {
                drawing = false;
            }

            canvas.addEventListener('mousedown', start);
            canvas.addEventListener('mousemove', move);
            window.addEventListener('mouseup', stop);
            canvas.addEventListener('touchstart', start, { passive: false });
            canvas.addEventListener('touchmove', move, { passive: false });
            canvas.addEventListener('touchend', stop);

            $(document).on('click', '.js-signature-clear', function () {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                hasStroke = false;
            });

            $(document).on('click', '.js-signature-save', function () {
                var $btn = $(this);
                var $error = $('.js-signature-error');
                $error.addClass('d-none').text('');

                if (!hasStroke) {
                    $error.removeClass('d-none').text($btn.data('empty-message') || 'Disegna la firma prima di salvare.');
                    return;
                }

                $btn.prop('disabled', true);
                Api.post($btn.data('url'), { signature: canvas.toDataURL('image/png') }).done(function (res) {
                    if (res && res.ok) {
                        window.location.reload();
                    } else {
                        $error.removeClass('d-none').text((res && res.error) || 'Errore imprevisto.');
                        $btn.prop('disabled', false);
                    }
                }).fail(function (xhr) {
                    $error.removeClass('d-none').text(failMessage(xhr));
                    $btn.prop('disabled', false);
                });
            });
        }
    });

    // --- Profile tabs deep-linking --------------------------------------------
    // #calendario / #interventi / #info in the URL opens that tab, and switching
    // tabs keeps the hash in sync so reload and shared links land on the same tab.
    $(function () {
        var $tabs = $('[data-app-tabs]');
        if (!$tabs.length || typeof bootstrap === 'undefined') {
            return;
        }
        var hash = window.location.hash;
        if (hash) {
            var btn = $tabs.find('button[data-bs-target="' + hash.replace(/[^#a-z-]/g, '') + '"]')[0];
            if (btn) {
                bootstrap.Tab.getOrCreateInstance(btn).show();
            }
        }
        $tabs.on('shown.bs.tab', 'button', function () {
            var target = $(this).attr('data-bs-target');
            if (target) {
                history.replaceState(null, '', target);
            }
        });
    });

    // --- Quotes: dynamic line editor (Preventivi form) -------------------------
    // Rows are added from a <template> with a per-form running index so the
    // lines[i][field] names stay unique; totals recompute live on every input.
    $(function () {
        var $editor = $('.js-quote-lines');
        if (!$editor.length) {
            return;
        }
        var index = parseInt($editor.attr('data-next-index'), 10) || 0;

        function formatEuro(v) {
            var parts = v.toFixed(2).split('.');
            return '€ ' + parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ',' + parts[1];
        }

        function recompute() {
            var subtotal = 0;
            $editor.find('.js-quote-line').each(function () {
                var qty = parseFloat($(this).find('[data-role="qty"]').val()) || 0;
                var price = parseFloat($(this).find('[data-role="price"]').val()) || 0;
                var line = qty * price;
                $(this).find('.js-quote-line-total').text(line > 0 ? formatEuro(line) : '—');
                subtotal += line;
            });
            var vat = parseFloat($('.js-quote-vat').val()) || 0;
            $('.js-quote-subtotal').text(formatEuro(subtotal));
            $('.js-quote-vat-amount').text(formatEuro(subtotal * vat / 100));
            $('.js-quote-total').text(formatEuro(subtotal * (1 + vat / 100)));
        }

        $(document).on('click', '.js-quote-add-line', function () {
            var html = $editor.find('.js-quote-line-template').html().replace(/__INDEX__/g, index++);
            $editor.find('.js-quote-lines-body').append(html);
            recompute();
        });

        $(document).on('click', '.js-quote-remove-line', function () {
            $(this).closest('.js-quote-line').remove();
            recompute();
        });

        $(document).on('input change', '.js-quote-lines input, .js-quote-vat', recompute);

        // The project dropdown only offers the selected client's projects; a
        // project belonging to another client is deselected on client change.
        var $project = $('.js-quote-project');
        function filterProjects() {
            var clientId = String($('.js-quote-client').val() || '');
            $project.find('option[data-client]').each(function () {
                var match = clientId !== '' && String($(this).data('client')) === clientId;
                $(this).prop('hidden', !match).prop('disabled', !match);
                if (!match && $(this).prop('selected')) {
                    $project.val('');
                }
            });
        }
        $(document).on('change', '.js-quote-client', filterProjects);
        filterProjects();

        // An empty editor starts with one blank row ready to fill in.
        if (!$editor.find('.js-quote-line').length) {
            $editor.find('.js-quote-add-line').trigger('click');
        }
        recompute();
    });

    // --- Filter date-range control -------------------------------------------
    // The per-input native calendar indicators are hidden (the group shows one
    // shared icon), so clicking anywhere in a "Dal / Al" field must open the
    // browser's date picker itself.
    $(function () {
        $(document).on('click', '.app-date-field input[type="date"]', function () {
            if (typeof this.showPicker === 'function') {
                try {
                    this.showPicker();
                } catch (e) {
                    // Ignore: picker already open or blocked; the field stays focusable.
                }
            }
        });
    });

    // --- Compliance: subject-type selector toggles the matching subject field --
    // Show the subject dropdown (operaio / subappaltatore / cantiere) that matches
    // the chosen "Soggetto", and disable the hidden ones so only the active
    // subject_id is submitted. "company" shows none (no subject_id needed).
    $(function () {
        $(document).on('change', '.js-compliance-subject-type', function () {
            var type = $(this).val();
            $('.js-compliance-subject').each(function () {
                var match = $(this).hasClass('js-compliance-subject-' + type);
                $(this).toggleClass('d-none', !match);
                $(this).find('select').prop('disabled', !match);
            });
        });
    });

    // --- Esportazioni: per-project report download ---------------------------
    // A project picker + PDF/Excel buttons reuse the existing per-project report
    // endpoints; pick a project, then the button navigates to its report file.
    $(function () {
        $(document).on('click', '.js-export-project-btn', function () {
            var $row = $(this).closest('.js-export-project-row');
            var $select = $row.find('.js-export-project');
            var id = $select.val();
            if (!id) {
                $select.trigger('focus');
                return;
            }
            window.location.href = $row.data('base') + '/' + id + '/report/' + $(this).data('format');
        });
    });

    // --- Shell: theme toggle + POST logout (Desktop build) --------------------
    // Theme is persisted in a cookie and rendered server-side (no flash); here we
    // just flip the live attribute and remember the choice for the next request.
    $(function () {
        $(document).on('click', '.js-theme-toggle', function () {
            var root = document.documentElement;
            var next = root.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
            root.setAttribute('data-bs-theme', next);
            document.cookie = 'gm_theme=' + next + ';path=/;max-age=31536000;samesite=lax';
            if (window.GM_drawSparks) { window.GM_drawSparks(); } // recolour to new tokens
        });

        // Logout is a POST (CSRF-protected); the navbar button replaces the old GET link.
        $(document).on('click', '.js-logout', function () {
            var url = $(this).data('url');
            Api.post('/logout', {}).always(function () {
                window.location.href = url ? url.replace(/\/logout$/, '/login') : (BASE + '/login');
            });
        });
    });

    // --- Change password (inline success/error) ------------------------------
    // Restored in the "juli" build: /password is an AJAX POST returning {ok},
    // so the page shows inline feedback instead of a raw form submit.
    $(function () {
        $(document).on('submit', '.js-password-form', function (e) {
            e.preventDefault();
            var $form = $(this);
            var $error = $form.closest('.card-body').find('.js-password-error');
            var $success = $form.closest('.card-body').find('.js-password-success');
            var $submit = $form.find('[type="submit"]');

            $error.addClass('d-none').text('');
            $success.addClass('d-none').text('');
            $submit.prop('disabled', true);

            Api.post('/password', $form.serialize()).done(function (res) {
                $submit.prop('disabled', false);
                if (res && res.ok) {
                    $form[0].reset();
                    $success.removeClass('d-none').text(GM.t('auth.password_updated', 'Password aggiornata correttamente.'));
                } else {
                    $error.removeClass('d-none').text((res && res.error) || GM.t('common.unexpected_error', 'Errore imprevisto.'));
                }
            }).fail(function (xhr) {
                $submit.prop('disabled', false);
                var msg = (xhr.responseJSON && xhr.responseJSON.error) || GM.t('common.connection_error', 'Errore di connessione.');
                $error.removeClass('d-none').text(msg);
            });
        });
    });

    // --- Badge di Cantiere: clock in/out with best-effort GPS + offline queue -
    // Restored in the "juli" build. Geolocation is optional (a worker is never
    // blocked by a denied/slow GPS); a timbratura made on a no-signal site is
    // queued in localStorage and replayed on reconnect.
    $(function () {
        function failMessage(xhr) {
            return (xhr.responseJSON && xhr.responseJSON.error) || GM.t('common.connection_error', 'Errore di connessione.');
        }

        function withGeolocation(callback) {
            var $geo = $('.js-attendance-geo');
            if (!navigator.geolocation) {
                callback({});
                return;
            }
            $geo.removeClass('d-none');
            navigator.geolocation.getCurrentPosition(function (pos) {
                $geo.addClass('d-none');
                callback({ lat: pos.coords.latitude.toFixed(7), lng: pos.coords.longitude.toFixed(7) });
            }, function () {
                $geo.addClass('d-none');
                callback({}); // denied / unavailable — proceed without coordinates
            }, { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 });
        }

        // Generic offline write queue (localStorage) for simple JSON POSTs — used
        // by the Badge di Cantiere so a timbratura on a no-signal site is not lost.
        // (Photos keep their own binary queue.)
        var ACTION_QUEUE_KEY = 'gm_action_queue_v1';
        function loadActionQueue() {
            try { return JSON.parse(window.localStorage.getItem(ACTION_QUEUE_KEY) || '[]'); } catch (e) { return []; }
        }
        function saveActionQueue(q) {
            try { window.localStorage.setItem(ACTION_QUEUE_KEY, JSON.stringify(q)); } catch (e) { /* best-effort */ }
        }
        function queueAction(url, data) {
            var q = loadActionQueue();
            q.push({ id: Date.now() + '-' + Math.random().toString(36).slice(2), url: url, data: data });
            saveActionQueue(q);
        }
        function flushActionQueue() {
            loadActionQueue().forEach(function (item) {
                Api.post(item.url, item.data).done(function () {
                    saveActionQueue(loadActionQueue().filter(function (q) { return q.id !== item.id; }));
                }).fail(function (xhr) {
                    if (xhr.status > 0) { // definite server response — drop it, not "offline"
                        saveActionQueue(loadActionQueue().filter(function (q) { return q.id !== item.id; }));
                    }
                });
            });
        }
        $(window).on('online', flushActionQueue);
        flushActionQueue();

        function postAttendance(url, data, $btn) {
            var $error = $('.js-attendance-error');
            $error.addClass('d-none').text('');
            $btn.prop('disabled', true);
            Api.post(url, data).done(function (res) {
                if (res && res.ok) {
                    window.location.reload();
                } else {
                    $error.removeClass('d-none alert-warning').addClass('alert-danger')
                        .text((res && res.error) || GM.t('common.unexpected_error', 'Errore imprevisto.'));
                    $btn.prop('disabled', false);
                }
            }).fail(function (xhr) {
                if (xhr.status === 0) {
                    queueAction(url, data);
                    $error.removeClass('d-none alert-danger').addClass('alert-warning')
                        .text(GM.t('attendance.offline_queued', 'Sei offline: la timbratura è stata salvata e verrà inviata alla riconnessione.'));
                    $btn.prop('disabled', false);
                    return;
                }
                $error.removeClass('d-none alert-warning').addClass('alert-danger').text(failMessage(xhr));
                $btn.prop('disabled', false);
            });
        }

        $(document).on('click', '.js-attendance-in', function () {
            var $btn = $(this);
            var projectId = $('.js-attendance-project').val();
            $btn.prop('disabled', true);
            withGeolocation(function (coords) {
                postAttendance($btn.data('url'), $.extend({ project_id: projectId }, coords), $btn);
            });
        });

        $(document).on('click', '.js-attendance-out', function () {
            var $btn = $(this);
            $btn.prop('disabled', true);
            withGeolocation(function (coords) {
                postAttendance($btn.data('url'), coords, $btn);
            });
        });
    });

    // --- Dashboard KPI sparklines --------------------------------------------
    // Draws every <canvas data-spark="n,n,..." data-c="ok|bad|warn|steel|amber">.
    function drawSparks() {
        var list = document.querySelectorAll('canvas[data-spark]');
        var palette = { ok: '#1F8A4C', bad: '#D33A2C', warn: '#E07C10', steel: '#2C6E9B', amber: '#2e7d32' };
        for (var k = 0; k < list.length; k++) {
            var cv = list[k];
            var pts = (cv.getAttribute('data-spark') || '').split(',').map(Number)
                .filter(function (n) { return !isNaN(n); });
            if (pts.length < 2) { continue; }
            var c = palette[cv.getAttribute('data-c')] || palette.steel;
            var dpr = window.devicePixelRatio || 1;
            var w = cv.clientWidth || 200;
            var h = cv.clientHeight || 34;
            cv.width = w * dpr; cv.height = h * dpr;
            var ctx = cv.getContext('2d');
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            ctx.clearRect(0, 0, w, h);
            var min = Math.min.apply(null, pts), max = Math.max.apply(null, pts);
            var rng = (max - min) || 1, pad = 3;
            var X = function (i) { return pad + i * (w - pad * 2) / (pts.length - 1); };
            var Y = function (v) { return pad + (1 - (v - min) / rng) * (h - pad * 2); };
            ctx.beginPath(); ctx.moveTo(X(0), h);
            for (var i = 0; i < pts.length; i++) { ctx.lineTo(X(i), Y(pts[i])); }
            ctx.lineTo(X(pts.length - 1), h); ctx.closePath();
            ctx.fillStyle = c + '22'; ctx.fill();
            ctx.beginPath();
            for (var j = 0; j < pts.length; j++) { j ? ctx.lineTo(X(j), Y(pts[j])) : ctx.moveTo(X(j), Y(pts[j])); }
            ctx.strokeStyle = c; ctx.lineWidth = 2; ctx.lineJoin = 'round'; ctx.stroke();
            ctx.beginPath(); ctx.arc(X(pts.length - 1), Y(pts[pts.length - 1]), 2.6, 0, 7);
            ctx.fillStyle = c; ctx.fill();
        }
    }
    window.GM_drawSparks = drawSparks;
    $(function () { drawSparks(); });
    $(window).on('resize', drawSparks);

    // --- Keyboard shortcuts (reference at /shortcuts) ------------------------
    // "?" opens the guide, "/" jumps to search, and "g" then a section key
    // navigates (admin only — the targets are admin pages). Kept in sync with
    // views/shortcuts.php. Never fires while the user is typing in a field.
    $(function () {
        var role = document.body.getAttribute('data-role') || '';
        var navMap = {
            d: '/admin', c: '/admin/clients', p: '/admin/projects', i: '/admin/interventions',
            q: '/admin/quotes', f: '/admin/invoices', s: '/admin/expenses', m: '/admin/warehouse',
            b: '/admin/attendance', u: '/admin/users', e: '/admin/exports'
        };
        var pendingG = false;
        var pendingTimer = null;

        function inField(el) {
            if (!el) { return false; }
            var tag = (el.tagName || '').toLowerCase();
            return tag === 'input' || tag === 'textarea' || tag === 'select' || el.isContentEditable;
        }
        function clearPendingG() {
            pendingG = false;
            if (pendingTimer) { window.clearTimeout(pendingTimer); pendingTimer = null; }
        }

        document.addEventListener('keydown', function (e) {
            if (e.altKey || e.ctrlKey || e.metaKey) { return; }
            if (inField(e.target)) { return; }

            if (e.key === '?') {
                e.preventDefault();
                window.location.href = BASE + '/shortcuts';
                return;
            }
            if (e.key === '/') {
                var $search = $('input[name="q"], input[type="search"]').filter(':visible').first();
                if ($search.length) {
                    e.preventDefault();
                    $search.trigger('focus');
                }
                return;
            }
            if (pendingG) {
                var dest = navMap[(e.key || '').toLowerCase()];
                clearPendingG();
                if (dest && role === 'admin') {
                    e.preventDefault();
                    window.location.href = BASE + dest;
                }
                return;
            }
            if (e.key === 'g' || e.key === 'G') {
                if (role !== 'admin') { return; }
                pendingG = true;
                pendingTimer = window.setTimeout(clearPendingG, 1200);
            }
        });
    });
}(jQuery));
