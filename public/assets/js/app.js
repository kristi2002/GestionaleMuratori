/* global jQuery */
(function ($) {
    'use strict';

    var BASE = document.body.getAttribute('data-base') || '';

    // Shared AJAX helper. Always flags the request as XHR so the server returns
    // JSON ({ ok, data?, error? }) instead of HTML.
    window.Api = {
        request: function (method, path, data) {
            return $.ajax({
                url: BASE + path,
                method: method,
                data: data,
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
        },
        post: function (path, data) { return this.request('POST', path, data); },
        get: function (path, data) { return this.request('GET', path, data); }
    };

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
}(jQuery));
