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

    // Decode a data: URL into a Blob (used to store compressed photos in the
    // outbox as binary rather than base64). Kept at module scope so both the
    // photo-upload handler and the legacy-queue migration can reach it.
    function dataUrlToBlob(dataUrl) {
        var parts = dataUrl.split(',');
        var mime = parts[0].match(/:(.*?);/)[1];
        var binary = window.atob(parts[1]);
        var bytes = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i++) { bytes[i] = binary.charCodeAt(i); }
        return new Blob([bytes], { type: mime });
    }

    // --- Offline outbox (IndexedDB) ------------------------------------------
    // Durable queue for writes made on a no-signal construction site: JSON POSTs
    // (Badge di Cantiere timbrature, intervention status/completion) and binary
    // photo uploads. Unlike the old localStorage queues it survives reloads and
    // heavy-photo days (Blobs, not base64) and — paired with the service worker's
    // Background Sync — flushes even with no tab open. A queued write is settled
    // and dropped on ANY definite server response, because every target endpoint
    // rejects a double-apply by domain invariant (a single open attendance; an
    // illegal status transition). Only a network failure (offline) keeps it; a
    // 401/403 (expired session) keeps it and asks the worker to sign in again.
    var Outbox = (function () {
        var DB_NAME = 'gm-outbox', STORE = 'writes', dbp = null, authExpired = false;

        function open() {
            if (dbp) { return dbp; }
            dbp = new Promise(function (resolve, reject) {
                if (!window.indexedDB) { reject(new Error('no-idb')); return; }
                var req = window.indexedDB.open(DB_NAME, 1);
                req.onupgradeneeded = function () {
                    if (!req.result.objectStoreNames.contains(STORE)) {
                        req.result.createObjectStore(STORE, { keyPath: 'id' });
                    }
                };
                req.onsuccess = function () { resolve(req.result); };
                req.onerror = function () { reject(req.error); };
            });
            return dbp;
        }

        function put(rec) {
            return open().then(function (db) {
                return new Promise(function (resolve, reject) {
                    var t = db.transaction(STORE, 'readwrite');
                    t.objectStore(STORE).put(rec);
                    t.oncomplete = function () { resolve(); };
                    t.onerror = function () { reject(t.error); };
                });
            });
        }

        function all() {
            return open().then(function (db) {
                return new Promise(function (resolve, reject) {
                    var out = [];
                    var cur = db.transaction(STORE, 'readonly').objectStore(STORE).openCursor();
                    cur.onsuccess = function () {
                        var c = cur.result;
                        if (c) { out.push(c.value); c.continue(); } else { resolve(out); }
                    };
                    cur.onerror = function () { reject(cur.error); };
                });
            });
        }

        function del(id) {
            return open().then(function (db) {
                return new Promise(function (resolve) {
                    var t = db.transaction(STORE, 'readwrite');
                    t.objectStore(STORE).delete(id);
                    t.oncomplete = function () { resolve(); };
                    t.onerror = function () { resolve(); };
                });
            });
        }

        function newId() { return String(Date.now()) + '-' + Math.random().toString(36).slice(2); }
        function absUrl(path) { return new URL(BASE + path, window.location.href).toString(); }

        function send(rec) {
            var headers = { 'X-CSRF-Token': rec.csrf || CSRF, 'X-Requested-With': 'XMLHttpRequest' };
            var opts = { method: 'POST', credentials: 'same-origin', headers: headers };
            if (rec.kind === 'photo') {
                var fd = new FormData();
                fd.append('photo', rec.blob, 'photo.jpg');
                fd.append('type', rec.type || '');
                opts.body = fd;
            } else {
                headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=UTF-8';
                opts.body = rec.body || '';
            }
            return fetch(rec.url, opts);
        }

        // Ask the service worker to replay the queue in the background (so a write
        // made offline still goes out if the worker closes the tab before signal
        // returns). Best-effort: unsupported on iOS Safari, where the page-side
        // flush on reconnect/foreground covers the same cases.
        function registerSync() {
            if (!('serviceWorker' in navigator)) { return; }
            navigator.serviceWorker.ready.then(function (reg) {
                if (reg.sync) { reg.sync.register('gm-outbox').catch(function () {}); }
            }).catch(function () {});
        }

        function enqueue(rec) {
            rec.id = newId();
            rec.csrf = CSRF;
            return put(rec).then(function () { updateOutboxBanner(); registerSync(); });
        }

        function flush() {
            if (!window.indexedDB) { return Promise.resolve(); }
            return all().then(function (items) {
                return Promise.all(items.map(function (rec) {
                    return send(rec).then(function (res) {
                        // Session/CSRF expired: keep the write and prompt re-login.
                        if (res.status === 401 || res.status === 403) { authExpired = true; return; }
                        authExpired = false;
                        return del(rec.id);
                    }).catch(function () { /* still offline — keep queued */ });
                }));
            }).then(function () { updateOutboxBanner(); }).catch(function () {});
        }

        function count() {
            if (!window.indexedDB) { return Promise.resolve(0); }
            return all().then(function (i) { return i.length; }).catch(function () { return 0; });
        }

        return {
            json: function (path, data) { return enqueue({ kind: 'json', url: absUrl(path), body: $.param(data || {}) }); },
            photo: function (path, type, blob) { return enqueue({ kind: 'photo', url: absUrl(path), type: type, blob: blob }); },
            flush: flush,
            count: count,
            authExpired: function () { return authExpired; }
        };
    })();

    // Shared pending-writes banner (element .js-offline-queue-banner, rendered on
    // the worker/attendance screens). Reflects the total queued count, or a
    // re-login prompt when the last flush hit an expired session.
    function updateOutboxBanner() {
        var $banner = $('.js-offline-queue-banner');
        if (!$banner.length) { return; }
        Outbox.count().then(function (n) {
            if (n <= 0) { $banner.addClass('d-none').text(''); return; }
            var txt;
            if (Outbox.authExpired()) {
                txt = GM.t('js.outbox_relogin', 'Modifiche in attesa: accedi di nuovo per sincronizzarle.');
            } else if (n === 1) {
                txt = GM.t('js.outbox_pending_one', '1 modifica in attesa di sincronizzazione.');
            } else {
                txt = GM.t('js.outbox_pending_many', '{n} modifiche in attesa di sincronizzazione.').replace('{n}', n);
            }
            $banner.removeClass('d-none').text(txt);
        });
    }

    // One-time migration of the pre-outbox localStorage queues into IndexedDB so
    // any write made offline on an older build is not lost on upgrade.
    function migrateLegacyQueues() {
        var pending = [];
        try {
            JSON.parse(window.localStorage.getItem('gm_photo_queue_v1') || '[]').forEach(function (it) {
                if (it && it.dataUrl) { pending.push(Outbox.photo(it.url, it.type, dataUrlToBlob(it.dataUrl))); }
            });
            window.localStorage.removeItem('gm_photo_queue_v1');
        } catch (e) { /* ignore malformed legacy data */ }
        try {
            JSON.parse(window.localStorage.getItem('gm_action_queue_v1') || '[]').forEach(function (it) {
                if (it && it.url) { pending.push(Outbox.json(it.url, it.data)); }
            });
            window.localStorage.removeItem('gm_action_queue_v1');
        } catch (e) { /* ignore malformed legacy data */ }
        return Promise.all(pending);
    }

    function flushOutbox() { Outbox.flush(); }
    $(window).on('online', flushOutbox);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) { flushOutbox(); } });
    $(function () { migrateLegacyQueues().then(flushOutbox); updateOutboxBanner(); });

    // --- Web Push opt-in -----------------------------------------------------
    // The worker taps "Attiva notifiche" (.js-enable-push) to grant permission and
    // subscribe; the subscription is POSTed to the server. If permission is already
    // granted we re-sync silently on load so a reinstalled service worker re-registers.
    var Push = (function () {
        function supported() {
            return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
        }
        function urlB64ToUint8(base64) {
            var pad = new Array((4 - base64.length % 4) % 4 + 1).join('=');
            var b64 = (base64 + pad).replace(/-/g, '+').replace(/_/g, '/');
            var raw = window.atob(b64);
            var out = new Uint8Array(raw.length);
            for (var i = 0; i < raw.length; i++) { out[i] = raw.charCodeAt(i); }
            return out;
        }
        function serverKey() {
            return Api.get('/push/public-key').then(function (res) {
                return res && res.ok && res.data && res.data.key ? res.data.key : null;
            });
        }
        function saveSub(sub) {
            var json = sub.toJSON();
            return Api.post('/push/subscribe', {
                endpoint: sub.endpoint,
                p256dh: (json.keys && json.keys.p256dh) || '',
                auth: (json.keys && json.keys.auth) || ''
            });
        }
        function subscribe() {
            return serverKey().then(function (key) {
                if (!key) { return null; }
                return navigator.serviceWorker.ready.then(function (reg) {
                    return reg.pushManager.getSubscription().then(function (existing) {
                        return existing || reg.pushManager.subscribe({
                            userVisibleOnly: true,
                            applicationServerKey: urlB64ToUint8(key)
                        });
                    });
                }).then(function (sub) {
                    return sub ? saveSub(sub).then(function () { return sub; }) : null;
                });
            });
        }
        return {
            supported: supported,
            enable: function () {
                if (!supported()) { return Promise.resolve('unsupported'); }
                return Notification.requestPermission().then(function (perm) {
                    if (perm !== 'granted') { return perm; }
                    return subscribe().then(function (sub) { return sub ? 'granted' : 'unsupported'; });
                });
            },
            resync: function () {
                if (supported() && Notification.permission === 'granted') {
                    subscribe().catch(function () { /* best-effort */ });
                }
            }
        };
    })();
    window.GM_Push = Push;

    function markPushEnabled($btn) {
        $btn.prop('disabled', true)
            .removeClass('btn-outline-primary')
            .addClass('btn-success')
            .html('<i class="bi bi-bell-fill"></i> ' + GM.t('push.enabled', 'Notifiche attive'));
    }

    $(document).on('click', '.js-enable-push', function () {
        var $btn = $(this);
        $btn.prop('disabled', true);
        Push.enable().then(function (state) {
            if (state === 'granted') {
                markPushEnabled($btn);
            } else if (state === 'unsupported') {
                Dialog.alert(GM.t('push.unsupported', 'Le notifiche non sono supportate su questo dispositivo o browser.'));
                $btn.prop('disabled', false);
            } else {
                Dialog.alert(GM.t('push.denied', 'Permesso notifiche negato. Puoi riattivarlo dalle impostazioni del browser.'));
                $btn.prop('disabled', false);
            }
        });
    });

    $(function () {
        Push.resync();
        if (Push.supported() && window.Notification && Notification.permission === 'granted') {
            $('.js-enable-push').each(function () { markPushEnabled($(this)); });
        }
    });

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
                .text(opts.okLabel || GM.t('js.ok', 'OK'));
            modal.show();
        }

        return {
            alert: function (message, done, title) {
                if (!ensure()) {
                    window.alert(message);
                    if (done) {
                        done();
                    }
                    return;
                }
                open({ title: title || GM.t('js.error', 'Errore'), message: message, cancel: false, onConfirm: done, onCancel: done });
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
                    title: opts.title || GM.t('js.confirm', 'Conferma'),
                    message: message,
                    cancel: true,
                    okLabel: opts.okLabel || GM.t('js.confirm', 'Conferma'),
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
            $submit.prop('disabled', true).text(GM.t('js.login_progress', 'Accesso…'));

            Api.post('/login', {
                email: $('#email').val(),
                password: $('#password').val()
            }).done(function (res) {
                if (res && res.ok && res.data && res.data.redirect) {
                    window.location.href = res.data.redirect;
                } else {
                    showError((res && res.error) || GM.t('common.unexpected_error', 'Errore imprevisto.'));
                }
            }).fail(function (xhr) {
                var msg = GM.t('common.connection_error', 'Errore di connessione.');
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                }
                showError(msg);
            });
        });

        function showError(msg) {
            $error.removeClass('d-none').text(msg);
            $submit.prop('disabled', false).text(GM.t('auth.login_submit', 'Accedi'));
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
            return (xhr.responseJSON && xhr.responseJSON.error) || GM.t('common.connection_error', 'Errore di connessione.');
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
                    $error.removeClass('d-none').text((res && res.error) || GM.t('common.unexpected_error', 'Errore imprevisto.'));
                    buttonReset($submit);
                }
            }).fail(function (xhr) {
                $error.removeClass('d-none').text(failMessage(xhr));
                buttonReset($submit);
            });
        }

        // User form: role-specific link fields — client accounts reveal the
        // client picker, subcontractor accounts reveal the company picker.
        $(document).on('change', '.js-user-role', function () {
            var role = $(this).val();
            $('.js-user-client-field').toggleClass('d-none', role !== 'client');
            $('.js-user-subcontractor-field').toggleClass('d-none', role !== 'subcontractor');
            $('.js-user-worker-field').toggleClass('d-none', role !== 'worker');
        });

        $(document).on('click', '.js-crud-delete', function () {
            var $btn = $(this);
            Dialog.confirm($btn.data('confirm') || GM.t('js.confirm_generic', 'Confermi?'), {
                okLabel: GM.t('common.delete', 'Elimina'),
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
                            Dialog.alert((res && res.error) || GM.t('common.unexpected_error', 'Errore imprevisto.'));
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
                        Dialog.alert((res && res.error) || GM.t('common.unexpected_error', 'Errore imprevisto.'));
                    }
                }).fail(function (xhr) {
                    Dialog.alert(failMessage(xhr));
                });
            };
            var message = $btn.data('confirm');
            if (message) {
                Dialog.confirm(message, {
                    okLabel: $btn.data('ok-label') || GM.t('js.confirm', 'Conferma'),
                    okClass: 'btn-success',
                    onConfirm: run
                });
            } else {
                run();
            }
        });

        // Dispatch board: reassign an intervention's worker from a <select>. Reloads
        // so the board regroups (and re-evaluates double-booking flags).
        $(document).on('change', '.js-reassign', function () {
            var $sel = $(this);
            $sel.prop('disabled', true);
            Api.post($sel.data('url'), { worker_id: $sel.val() }).done(function (res) {
                if (res && res.ok) {
                    window.location.reload();
                } else {
                    Dialog.alert((res && res.error) || GM.t('common.unexpected_error', 'Errore imprevisto.'));
                    $sel.prop('disabled', false);
                }
            }).fail(function (xhr) {
                Dialog.alert(failMessage(xhr));
                $sel.prop('disabled', false);
            });
        });

        // Admin "send test e-mail": posts and reports the outcome inline (no reload),
        // so the SMTP config can be verified from the notifications page.
        $(document).on('click', '.js-test-email', function () {
            var $btn = $(this);
            $btn.prop('disabled', true);
            Api.post($btn.data('url'), {}).done(function (res) {
                Dialog.alert(
                    (res && res.data && res.data.message) || GM.t('js.ok', 'OK'),
                    null,
                    GM.t('js.notice', 'Avviso')
                );
            }).fail(function (xhr) {
                Dialog.alert(failMessage(xhr));
            }).always(function () {
                $btn.prop('disabled', false);
            });
        });

        $(document).on('click', '.js-toggle-active', function () {
            Api.post($(this).data('url'), {}).done(function (res) {
                if (res && res.ok) {
                    window.location.reload();
                } else {
                    Dialog.alert((res && res.error) || GM.t('common.unexpected_error', 'Errore imprevisto.'));
                }
            }).fail(function (xhr) {
                Dialog.alert(failMessage(xhr));
            });
        });

        // Lead inbox: set a lead's status (posts {status} from data-status).
        $(document).on('click', '.js-lead-status', function () {
            var $btn = $(this);
            Api.post($btn.data('url'), { status: $btn.data('status') }).done(function (res) {
                if (res && res.ok) {
                    window.location.reload();
                } else {
                    Dialog.alert((res && res.error) || GM.t('common.unexpected_error', 'Errore imprevisto.'));
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
                    Dialog.alert((res && res.error) || $cell.closest('[data-att-error]').data('att-error') || GM.t('common.unexpected_error', 'Errore imprevisto.'));
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
                    Dialog.alert((res && res.error) || GM.t('common.unexpected_error', 'Errore imprevisto.'));
                }
            }).fail(function (xhr) {
                buttonReset($submit);
                Dialog.alert(failMessage(xhr));
            });
        });

        $(document).on('click', '.js-att-remove', function (e) {
            e.stopPropagation(); // do not also select the worker card being removed
            var $btn = $(this);
            Dialog.confirm($btn.data('confirm') || GM.t('js.confirm_generic', 'Confermi?'), {
                okLabel: GM.t('js.remove', 'Rimuovi'),
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
                    Dialog.alert((res && res.error) || GM.t('common.unexpected_error', 'Errore imprevisto.'));
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
                    $error.removeClass('d-none').text((res && res.error) || GM.t('common.unexpected_error', 'Errore imprevisto.'));
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
                    $result.removeClass('d-none alert-info').addClass('alert-danger').text((res && res.error) || GM.t('common.unexpected_error', 'Errore imprevisto.'));
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
                    Dialog.alert((res && res.error) || GM.t('common.unexpected_error', 'Errore imprevisto.'));
                    $sel.val(previous).prop('disabled', false);
                }
            }).fail(function (xhr) {
                if (xhr.status === 0) {
                    // No signal: queue the transition and keep the chosen value shown.
                    Outbox.json($sel.data('url'), { to_status: toStatus });
                    Dialog.alert(GM.t('js.status_offline_queued', 'Sei offline: la modifica di stato è stata salvata sul dispositivo e verrà sincronizzata alla riconnessione.'));
                    $sel.prop('disabled', false);
                    return;
                }
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
                    Dialog.alert((res && res.error) || GM.t('common.unexpected_error', 'Errore imprevisto.'));
                }
            }).fail(function (xhr) {
                if (xhr.status === 0) {
                    Outbox.json($btn.data('url'), { to_status: $btn.data('to-status') });
                    Dialog.alert(GM.t('js.status_offline_queued', 'Sei offline: la modifica di stato è stata salvata sul dispositivo e verrà sincronizzata alla riconnessione.'));
                    return;
                }
                Dialog.alert(failMessage(xhr));
            });
        }

        // --- Intervention checklist: tick items (offline-capable) + admin manage --
        function refreshTaskProgress($el) {
            var $card = $el.closest('.card');
            var $badge = $card.find('.js-task-progress');
            if (!$badge.length) { return; }
            var total = $card.find('.js-task-toggle').length;
            var done = $card.find('.js-task-toggle:checked').length;
            $badge.attr('data-done', done).attr('data-total', total).text(done + '/' + total);
        }
        function styleTaskLabel($cb, done) {
            $cb.closest('.form-check, .d-flex').find('.js-task-label')
                .toggleClass('text-decoration-line-through text-muted', done === 1);
        }

        // Toggle is an ABSOLUTE set ({done:1|0}), so a replayed offline write is idempotent.
        $(document).on('change', '.js-task-toggle', function () {
            var $cb = $(this);
            var done = $cb.is(':checked') ? 1 : 0;
            var url = $cb.data('url');
            styleTaskLabel($cb, done);
            refreshTaskProgress($cb);
            Api.post(url, { done: done }).done(function (res) {
                if (!(res && res.ok)) {
                    $cb.prop('checked', done !== 1);
                    styleTaskLabel($cb, done !== 1 ? 1 : 0);
                    refreshTaskProgress($cb);
                    Dialog.alert((res && res.error) || GM.t('common.unexpected_error', 'Errore imprevisto.'));
                }
            }).fail(function (xhr) {
                if (xhr.status === 0) {
                    Outbox.json(url, { done: done }); // offline — queue and stay optimistic
                    return;
                }
                $cb.prop('checked', done !== 1);
                styleTaskLabel($cb, done !== 1 ? 1 : 0);
                refreshTaskProgress($cb);
                Dialog.alert(failMessage(xhr));
            });
        });

        // Admin: add a checklist item.
        $(document).on('submit', '.js-task-add-form', function (e) {
            e.preventDefault();
            var $form = $(this);
            var label = $.trim($form.find('input[name="label"]').val());
            if (!label) { return; }
            Api.post($form.data('url'), { label: label }).done(function (res) {
                if (res && res.ok) { window.location.reload(); }
                else { Dialog.alert((res && res.error) || GM.t('common.unexpected_error', 'Errore imprevisto.')); }
            }).fail(function (xhr) { Dialog.alert(failMessage(xhr)); });
        });

        // Admin: delete a checklist item.
        $(document).on('click', '.js-task-delete', function () {
            var $btn = $(this);
            Dialog.confirm($btn.data('confirm'), {
                onConfirm: function () {
                    Api.post($btn.data('url')).done(function (res) {
                        if (res && res.ok) { window.location.reload(); }
                        else { Dialog.alert((res && res.error) || GM.t('common.unexpected_error', 'Errore imprevisto.')); }
                    }).fail(function (xhr) { Dialog.alert(failMessage(xhr)); });
                }
            });
        });

        // --- Worker: photo upload (offline-friendly via the outbox) --------------
        // Compresses client-side before upload; on a network failure (offline —
        // distinct from a server-side validation rejection) the compressed photo is
        // stored in the IndexedDB outbox and replayed automatically on reconnect.
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
                        showPhotoMessage($error, (res && res.error) || GM.t('common.unexpected_error', 'Errore imprevisto.'), false);
                        $submit.prop('disabled', false);
                    }
                }).fail(function (xhr) {
                    if (xhr.status > 0) {
                        // The server responded (e.g. 401/500) — a real failure, not "offline".
                        showPhotoMessage($error, failMessage(xhr), false);
                        $submit.prop('disabled', false);
                        return;
                    }
                    Outbox.photo(url, type, dataUrlToBlob(dataUrl));
                    showPhotoMessage($error, GM.t('js.photo_offline_queued', 'Sei offline: la foto è stata salvata sul dispositivo e verrà caricata automaticamente alla riconnessione.'), true);
                    $submit.prop('disabled', false);
                    $form[0].reset();
                });
            }).catch(function () {
                showPhotoMessage($error, 'Impossibile elaborare l\'immagine selezionata.', false);
                $submit.prop('disabled', false);
            });
        });

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
                        $error.removeClass('d-none').text((res && res.error) || GM.t('common.unexpected_error', 'Errore imprevisto.'));
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

    // Auto-submit a form when a marked control changes (e.g. calendar month jump).
    $(document).on('change', '.js-auto-submit', function () {
        if (this.form) { this.form.submit(); }
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

    // --- Purchase orders: dynamic line editor (Buoni d'Ordine form) ------------
    // Same running-index line editor as quotes, with an extra item <select> per row:
    // picking a warehouse item fills the description/unit when they are still blank.
    $(function () {
        var $editor = $('.js-po-lines');
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
            $editor.find('.js-po-line').each(function () {
                var qty = parseFloat($(this).find('[data-role="qty"]').val()) || 0;
                var price = parseFloat($(this).find('[data-role="price"]').val()) || 0;
                var line = qty * price;
                $(this).find('.js-po-line-total').text(line > 0 ? formatEuro(line) : '—');
                subtotal += line;
            });
            var vat = parseFloat($('.js-po-vat').val()) || 0;
            $('.js-po-subtotal').text(formatEuro(subtotal));
            $('.js-po-vat-amount').text(formatEuro(subtotal * vat / 100));
            $('.js-po-total').text(formatEuro(subtotal * (1 + vat / 100)));
        }

        $(document).on('click', '.js-po-add-line', function () {
            var html = $editor.find('.js-po-line-template').html().replace(/__INDEX__/g, index++);
            $editor.find('.js-po-lines-body').append(html);
            recompute();
        });

        $(document).on('click', '.js-po-remove-line', function () {
            $(this).closest('.js-po-line').remove();
            recompute();
        });

        // Choosing an item pre-fills the row's description and unit if left blank.
        $(document).on('change', '.js-po-item', function () {
            var $opt = $(this).find('option:selected');
            var $row = $(this).closest('.js-po-line');
            var name = $opt.data('name');
            var unit = $opt.data('unit');
            if (name) {
                var $desc = $row.find('[name$="[description]"]');
                if (!$desc.val()) { $desc.val(name); }
            }
            if (unit) {
                var $unit = $row.find('[name$="[unit]"]');
                if (!$unit.val()) { $unit.val(unit); }
            }
        });

        $(document).on('input change', '.js-po-lines input, .js-po-vat', recompute);

        // An empty editor starts with one blank row ready to fill in.
        if (!$editor.find('.js-po-line').length) {
            $editor.find('.js-po-add-line').trigger('click');
        }
        recompute();
    });

    // --- Filter date-range control: custom themeable calendar popup ----------
    // The native date-picker overlay can't be styled, so for the "Dal / Al"
    // fields we render our own in-page calendar (month names / weekday labels
    // come from data- attributes so they stay translated).
    $(function () {
        var $range = $('.app-date-range');
        if (!$range.length) { return; }

        var months = [], weekdays = [];
        try { months = JSON.parse($range.attr('data-months') || '[]'); } catch (e) {}
        try { weekdays = JSON.parse($range.attr('data-weekdays') || '[]'); } catch (e) {}
        if (months.length !== 12) { months = ['1','2','3','4','5','6','7','8','9','10','11','12']; }

        var $pop = null, input = null, vy = 0, vm = 0;
        function pad(n) { return (n < 10 ? '0' : '') + n; }
        function fmt(y, m, d) { return y + '-' + pad(m + 1) + '-' + pad(d); }

        function close() {
            if ($pop) { $pop.remove(); $pop = null; input = null; $(document).off('.appdp'); }
        }

        function render() {
            var first = new Date(vy, vm, 1);
            var lead = (first.getDay() + 6) % 7;               // Monday-first
            var days = new Date(vy, vm + 1, 0).getDate();
            var t = new Date(), sel = input && input.value ? input.value : '';
            var h = '<div class="app-dp-head">'
                  + '<button type="button" class="app-dp-nav" data-dp="prev" aria-label="-">&lsaquo;</button>'
                  + '<span class="app-dp-title">' + months[vm] + ' ' + vy + '</span>'
                  + '<button type="button" class="app-dp-nav" data-dp="next" aria-label="+">&rsaquo;</button></div>'
                  + '<div class="app-dp-weekdays">';
            for (var w = 0; w < 7; w++) { h += '<span>' + (weekdays[w] || '') + '</span>'; }
            h += '</div><div class="app-dp-grid">';
            for (var i = 0; i < lead; i++) { h += '<span class="app-dp-day is-empty"></span>'; }
            for (var d = 1; d <= days; d++) {
                var c = 'app-dp-day';
                if (vy === t.getFullYear() && vm === t.getMonth() && d === t.getDate()) { c += ' is-today'; }
                if (sel === fmt(vy, vm, d)) { c += ' is-selected'; }
                h += '<button type="button" class="' + c + '" data-d="' + d + '">' + d + '</button>';
            }
            $pop.html(h + '</div>');
        }

        function open(el) {
            close();
            input = el;
            var p = el.value ? el.value.split('-') : null;
            var base = p ? new Date(+p[0], +p[1] - 1, +p[2]) : new Date();
            vy = base.getFullYear(); vm = base.getMonth();
            $pop = $('<div class="app-dp"></div>').appendTo(document.body);
            render();
            var r = el.getBoundingClientRect();
            $pop.css({ top: (window.pageYOffset + r.bottom + 4) + 'px', left: (window.pageXOffset + r.left) + 'px' });
            setTimeout(function () {
                $(document).on('mousedown.appdp', function (ev) {
                    if ($pop && !$pop[0].contains(ev.target) && ev.target !== el) { close(); }
                });
                $(document).on('keydown.appdp', function (ev) { if (ev.key === 'Escape') { close(); } });
            }, 0);
        }

        $(document).on('click', '.app-date-field input[type="date"]', function () { open(this); });
        $(document).on('click', '.app-dp [data-dp]', function () {
            if ($(this).attr('data-dp') === 'prev') { if (--vm < 0) { vm = 11; vy--; } }
            else { if (++vm > 11) { vm = 0; vy++; } }
            render();
        });
        $(document).on('click', '.app-dp-day[data-d]', function () {
            if (input) { input.value = fmt(vy, vm, +$(this).attr('data-d')); $(input).trigger('change'); }
            close();
        });
    });

    // --- Interventions: planned-material editor (add / remove rows) ----------
    // The create form lists item_id[]/qty_planned[] rows; "Aggiungi materiale"
    // clones the <template> row, and each row's × removes it.
    $(function () {
        $(document).on('click', '.js-material-add', function () {
            var $section = $(this).closest('.js-materials-section');
            var tpl = $section.find('.js-material-template').html();
            $section.find('.js-materials-rows').append(tpl);
        });
        $(document).on('click', '.js-material-remove', function () {
            $(this).closest('.js-material-row').remove();
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
                    $success.removeClass('d-none').text(GM.t('auth.password_changed', 'Password aggiornata correttamente.'));
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
                    Outbox.json(url, data);
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
        var palette = { ok: '#10B981', bad: '#EF4444', warn: '#F59E0B', steel: '#3B82F6', amber: '#F97316' };
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
        // Defaults (fallback); the server injects the user's effective key->href
        // map (incl. any customisations) into body[data-shortcuts].
        var navMap = {
            d: '/admin', t: '/admin/statistics', r: '/admin/financials', c: '/admin/clients',
            p: '/admin/projects', i: '/admin/interventions', q: '/admin/quotes', f: '/admin/invoices',
            s: '/admin/expenses', m: '/admin/warehouse', b: '/admin/attendance', u: '/admin/users', e: '/admin/exports'
        };
        try {
            var custom = JSON.parse(document.body.getAttribute('data-shortcuts') || '');
            if (custom && typeof custom === 'object' && Object.keys(custom).length) { navMap = custom; }
        } catch (err) { /* keep defaults */ }
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

    // --- Keyboard-shortcut editor (/shortcuts, admins) -----------------------
    // Each row has a single-letter key input; Save posts the {action: key} map,
    // the server validates (single letter, unique, "g" reserved) and persists it.
    $(function () {
        var $form = $('.js-shortcuts-form');
        if (!$form.length) { return; }
        var $msg  = $form.find('.js-shortcuts-msg');
        var $keys = $form.find('.js-shortcut-key');

        function normalize() {
            $keys.each(function () {
                this.value = (this.value || '').replace(/[^a-zA-Z]/g, '').toUpperCase().slice(0, 1);
            });
            var seen = {};
            $keys.each(function () { if (this.value) { seen[this.value] = (seen[this.value] || 0) + 1; } });
            $keys.each(function () { $(this).toggleClass('is-dup', !!this.value && seen[this.value] > 1); });
        }

        $form.on('input', '.js-shortcut-key', normalize);
        normalize();

        $form.find('.js-shortcuts-reset').on('click', function () {
            $keys.each(function () { this.value = $(this).data('default'); });
            normalize();
            $form.trigger('submit');
        });

        $form.on('submit', function (e) {
            e.preventDefault();
            var payload = {};
            $keys.each(function () {
                var m = /^shortcuts\[(.+)\]$/.exec(this.name || '');
                if (m) { payload[m[1]] = (this.value || '').toLowerCase(); }
            });
            $msg.removeClass('text-success text-danger').text('');
            $.ajax({
                url: $form.data('url'),
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ shortcuts: payload })
            }).done(function (res) {
                if (res && res.ok) {
                    $msg.addClass('text-success').text($form.data('saved') || GM.t('js.ok', 'OK'));
                } else {
                    $msg.addClass('text-danger').text((res && res.error) || '');
                }
            }).fail(function (xhr) {
                $msg.addClass('text-danger').text((xhr.responseJSON && xhr.responseJSON.error) || '');
            });
        });
    });
}(jQuery));
