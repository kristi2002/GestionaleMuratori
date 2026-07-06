/* global jQuery */
(function ($) {
    'use strict';

    var BASE = document.body.getAttribute('data-base') || '';
    var CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    // Every AJAX request carries the CSRF token; the server rejects POSTs without it.
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

    // --- Logout (POST + CSRF; the navbar button replaces the old GET link) ---
    $(function () {
        $(document).on('click', '.js-logout', function () {
            var url = $(this).data('url');
            Api.post('/logout', {}).always(function () {
                window.location.href = url ? url.replace(/\/logout$/, '/login') : (BASE + '/login');
            });
        });
    });

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

    // --- Change password ------------------------------------------------------
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
                    $success.removeClass('d-none').text('Password aggiornata correttamente.');
                } else {
                    $error.removeClass('d-none').text((res && res.error) || 'Errore imprevisto.');
                }
            }).fail(function (xhr) {
                $submit.prop('disabled', false);
                var msg = (xhr.responseJSON && xhr.responseJSON.error) || 'Errore di connessione.';
                $error.removeClass('d-none').text(msg);
            });
        });
    });

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
            if (confirmMsg && !window.confirm(confirmMsg)) {
                return;
            }
            var id = $form.find('[name="id"]').val();
            var url = id ? $form.data('base-url') + '/' + id : $form.data('base-url');
            var $error = $form.find('.js-crud-error');
            var $submit = $form.find('[type="submit"]');

            $error.addClass('d-none').text('');
            $submit.prop('disabled', true);

            Api.post(url, $form.serialize()).done(function (res) {
                if (res && res.ok) {
                    window.location.reload();
                } else {
                    $error.removeClass('d-none').text((res && res.error) || 'Errore imprevisto.');
                    $submit.prop('disabled', false);
                }
            }).fail(function (xhr) {
                $error.removeClass('d-none').text(failMessage(xhr));
                $submit.prop('disabled', false);
            });
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
            if (!window.confirm($btn.data('confirm') || 'Confermi?')) {
                return;
            }
            Api.post($btn.data('url'), {}).done(function (res) {
                if (res && res.ok) {
                    window.location.reload();
                } else {
                    window.alert((res && res.error) || 'Errore imprevisto.');
                }
            }).fail(function (xhr) {
                window.alert(failMessage(xhr));
            });
        });

        $(document).on('click', '.js-toggle-active', function () {
            Api.post($(this).data('url'), {}).done(function (res) {
                if (res && res.ok) {
                    window.location.reload();
                } else {
                    window.alert((res && res.error) || 'Errore imprevisto.');
                }
            }).fail(function (xhr) {
                window.alert(failMessage(xhr));
            });
        });

        // --- Users: the client / subcontractor dropdowns are role-specific --------
        function syncUserClientField() {
            var $modal = $('#user-modal');
            if (!$modal.length) {
                return;
            }
            var role = $modal.find('.js-user-role').val();
            var isClient = role === 'client';
            var isSub = role === 'subcontractor';
            $modal.find('.js-user-client-field').toggleClass('d-none', !isClient);
            $modal.find('.js-user-subcontractor-field').toggleClass('d-none', !isSub);
            if (!isClient) {
                $modal.find('[name="client_id"]').val('');
            }
            if (!isSub) {
                $modal.find('[name="subcontractor_id"]').val('');
            }
        }
        $(document).on('change', '.js-user-role', syncUserClientField);
        $(document).on('shown.bs.modal', '#user-modal', syncUserClientField);

        // --- Compliance: subject dropdown depends on the subject type ------------
        // Only the matching subject <select> is shown AND enabled, so exactly one
        // subject_id is serialized (disabled fields are not submitted). 'company'
        // has no subject.
        function syncComplianceSubject() {
            var $modal = $('#compliance-modal');
            if (!$modal.length) {
                return;
            }
            var type = $modal.find('.js-compliance-subject-type').val();
            $modal.find('.js-compliance-subject').each(function () {
                var $wrap = $(this);
                var match = $wrap.hasClass('js-compliance-subject-' + type);
                $wrap.toggleClass('d-none', !match);
                $wrap.find('select').prop('disabled', !match);
            });
        }
        $(document).on('change', '.js-compliance-subject-type', syncComplianceSubject);
        $(document).on('shown.bs.modal', '#compliance-modal', syncComplianceSubject);

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

        // --- Interventions: status transition buttons -------------------------
        $(document).on('click', '.js-intervention-status', function () {
            var $btn = $(this);
            var confirmMsg = $btn.data('confirm');
            if (confirmMsg && !window.confirm(confirmMsg)) {
                return;
            }
            Api.post($btn.data('url'), { to_status: $btn.data('to-status') }).done(function (res) {
                if (res && res.ok) {
                    window.location.reload();
                } else {
                    window.alert((res && res.error) || 'Errore imprevisto.');
                }
            }).fail(function (xhr) {
                window.alert(failMessage(xhr));
            });
        });

        // --- Interventions: repeatable material rows + create/edit mode toggle --
        var $materialsSection = $('.js-materials-section');
        var $projectField = $('.js-intervention-project-field');
        var $rows = $('.js-materials-rows');

        function emptyMaterialRow() {
            var $row = $rows.find('.js-material-row').first().clone();
            $row.find('select, input').val('');
            return $row;
        }

        $(document).on('click', '.js-material-add', function () {
            $rows.append(emptyMaterialRow());
        });

        $(document).on('click', '.js-material-remove', function () {
            var $row = $(this).closest('.js-material-row');
            if ($rows.find('.js-material-row').length > 1) {
                $row.remove();
            } else {
                $row.find('select, input').val('');
            }
        });

        $(document).on('click', '.js-intervention-new', function () {
            $materialsSection.removeClass('d-none');
            $projectField.removeClass('d-none');
            $rows.find('.js-material-row').slice(1).remove();
            $rows.find('select, input').val('');
        });

        $(document).on('click', '.js-intervention-edit', function () {
            $materialsSection.addClass('d-none');
            $projectField.addClass('d-none');
        });

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

        // Best-effort device geotag (photos): resolves {lat,lng} or {} without any UI.
        function capturePhotoCoords() {
            return new Promise(function (resolve) {
                if (!navigator.geolocation) {
                    resolve({});
                    return;
                }
                navigator.geolocation.getCurrentPosition(function (pos) {
                    resolve({ lat: pos.coords.latitude.toFixed(7), lng: pos.coords.longitude.toFixed(7) });
                }, function () {
                    resolve({});
                }, { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 });
            });
        }

        function uploadPhotoBlob(url, type, blob, meta) {
            meta = meta || {};
            var data = new FormData();
            data.append('photo', blob, 'photo.jpg');
            data.append('type', type);
            if (meta.lat != null && meta.lng != null) {
                data.append('lat', meta.lat);
                data.append('lng', meta.lng);
            }
            if (meta.captured_at != null) {
                data.append('captured_at', meta.captured_at);
            }
            return $.ajax({
                url: BASE + url,
                method: 'POST',
                data: data,
                processData: false,
                contentType: false,
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': CSRF }
            });
        }

        function queuePhotoForRetry(url, type, dataUrl, meta) {
            var queue = loadPhotoQueue();
            queue.push({ id: Date.now() + '-' + Math.random().toString(36).slice(2), url: url, type: type, dataUrl: dataUrl, meta: meta || {} });
            savePhotoQueue(queue);
            updateQueueBanner();
        }

        function flushPhotoQueue() {
            loadPhotoQueue().forEach(function (item) {
                uploadPhotoBlob(item.url, item.type, dataUrlToBlob(item.dataUrl), item.meta || {}).always(function () {
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

            // Geotag + capture time travel with the photo (S.A.L. evidence), and are
            // preserved in the offline queue so a reconnected upload keeps its metadata.
            Promise.all([
                compressImageToDataUrl($file[0].files[0], 1600, 0.8),
                capturePhotoCoords()
            ]).then(function (results) {
                var dataUrl = results[0];
                var meta = results[1] || {};
                meta.captured_at = Date.now();

                uploadPhotoBlob(url, type, dataUrlToBlob(dataUrl), meta).done(function (res) {
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
                    queuePhotoForRetry(url, type, dataUrl, meta);
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

        // --- Badge di Cantiere: clock in/out with best-effort GPS ----------------
        // Geolocation is optional: if the browser denies it or times out we still
        // record the timbratura (timestamp only), so a worker is never blocked.
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

        // Generic offline write queue (localStorage) for simple JSON POSTs — used by
        // the Badge di Cantiere so a timbratura on a no-signal site is not lost; it
        // replays automatically on reconnect. (Photos have their own binary queue.)
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
                    $error.removeClass('d-none').text((res && res.error) || 'Errore imprevisto.');
                    $btn.prop('disabled', false);
                }
            }).fail(function (xhr) {
                if (xhr.status === 0) {
                    // Offline — persist the timbratura and sync on reconnect.
                    queueAction(url, data);
                    $error.removeClass('d-none alert-danger').addClass('alert-warning')
                        .text('Sei offline: la timbratura è stata salvata e verrà inviata alla riconnessione.');
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
}(jQuery));
