/*
 * Service worker for the worker/field PWA. Cache-first for the static app shell
 * (so the JS runs on a site with no signal — the offline photo/attendance queue
 * then does its job), network-first for everything else with a graceful offline
 * notice for navigations. Scope-relative so it works at a domain root or a subdir.
 */
'use strict';

// Bump this on every material change to the cached shell assets (app.css/js,
// vendored Bootstrap/jQuery) — the `activate` handler deletes any cache whose
// name differs, so a bump is what forces returning clients to fetch fresh assets
// instead of serving the stale cache-first copy.
var VERSION = 'gm-shell-v31';
var SCOPE = self.registration.scope; // e.g. https://host/  or  https://host/app/public/

function scoped(path) {
    return new URL(path, SCOPE).toString();
}

var SHELL = [
    scoped('assets/css/app.css'),
    scoped('assets/js/app.js'),
    scoped('assets/vendor/bootstrap.min.css'),
    scoped('assets/vendor/bootstrap.bundle.min.js'),
    scoped('assets/vendor/bootstrap-icons.min.css'),
    scoped('assets/vendor/fonts/bootstrap-icons.woff2'),
    scoped('assets/vendor/jquery.min.js'),
    // Self-hosted Inter web-fonts: without these the offline shell falls back to
    // system fonts. Weights match the @font-face rules in app.css.
    scoped('assets/fonts/inter-latin-400-normal.woff2'),
    scoped('assets/fonts/inter-latin-500-normal.woff2'),
    scoped('assets/fonts/inter-latin-600-normal.woff2'),
    scoped('assets/fonts/inter-latin-700-normal.woff2'),
    scoped('assets/fonts/inter-latin-800-normal.woff2'),
    scoped('assets/icons/icon-192.png'),
    scoped('assets/icons/icon-512.png'),
    scoped('offline.html')
];

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(VERSION).then(function (cache) {
            // Best-effort: a single missing asset must not fail the whole install.
            return Promise.all(SHELL.map(function (url) {
                return cache.add(url).catch(function () { return null; });
            }));
        }).then(function () { return self.skipWaiting(); })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(keys.filter(function (k) { return k !== VERSION; })
                .map(function (k) { return caches.delete(k); }));
        }).then(function () { return self.clients.claim(); })
    );
});

self.addEventListener('fetch', function (event) {
    var req = event.request;
    if (req.method !== 'GET' || new URL(req.url).origin !== self.location.origin) {
        return; // never touch POSTs (uploads/queue) or cross-origin
    }

    // Static shell assets: cache-first.
    if (req.url.indexOf('/assets/') !== -1) {
        event.respondWith(
            caches.match(req).then(function (hit) {
                return hit || fetch(req).then(function (res) {
                    var copy = res.clone();
                    caches.open(VERSION).then(function (c) { c.put(req, copy); });
                    return res;
                });
            })
        );
        return;
    }

    // Navigations: network-first, fall back to a cached offline notice.
    if (req.mode === 'navigate') {
        event.respondWith(
            fetch(req).catch(function () {
                return caches.match(scoped('offline.html')).then(function (hit) {
                    return hit || new Response('Offline', { status: 503, headers: { 'Content-Type': 'text/plain' } });
                });
            })
        );
    }
});

// --- Offline outbox flush (mirrors the page-side Outbox in app.js) ----------
// Durable IndexedDB queue of writes made on a no-signal site (timbrature,
// intervention status/completion, photos). The page flushes on reconnect; this
// SW copy flushes on Background Sync so queued writes still go out with no tab
// open. Each record carries its own (session-stable) CSRF token so the replayed
// POST passes the front-controller CSRF check, and its absolute URL so it works
// under a subdirectory deploy. A record is settled — and deleted — on any
// definite response except a 401/403 (session/CSRF expired), which keeps it
// queued for a re-authenticated retry; a network failure keeps it too.
var OUTBOX_DB = 'gm-outbox', OUTBOX_STORE = 'writes';

function outboxDb() {
    return new Promise(function (resolve, reject) {
        var req = indexedDB.open(OUTBOX_DB, 1);
        req.onupgradeneeded = function () {
            if (!req.result.objectStoreNames.contains(OUTBOX_STORE)) {
                req.result.createObjectStore(OUTBOX_STORE, { keyPath: 'id' });
            }
        };
        req.onsuccess = function () { resolve(req.result); };
        req.onerror = function () { reject(req.error); };
    });
}

function outboxAll(db) {
    return new Promise(function (resolve, reject) {
        var out = [];
        var cur = db.transaction(OUTBOX_STORE, 'readonly').objectStore(OUTBOX_STORE).openCursor();
        cur.onsuccess = function () {
            var c = cur.result;
            if (c) { out.push(c.value); c.continue(); } else { resolve(out); }
        };
        cur.onerror = function () { reject(cur.error); };
    });
}

function outboxDelete(db, id) {
    return new Promise(function (resolve) {
        var t = db.transaction(OUTBOX_STORE, 'readwrite');
        t.objectStore(OUTBOX_STORE).delete(id);
        t.oncomplete = function () { resolve(); };
        t.onerror = function () { resolve(); };
    });
}

function sendOutboxRecord(rec) {
    var headers = { 'X-CSRF-Token': rec.csrf || '', 'X-Requested-With': 'XMLHttpRequest' };
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

function flushOutbox() {
    return outboxDb().then(function (db) {
        return outboxAll(db).then(function (items) {
            return Promise.all(items.map(function (rec) {
                return sendOutboxRecord(rec).then(function (res) {
                    if (res.status !== 401 && res.status !== 403) { return outboxDelete(db, rec.id); }
                }).catch(function () { /* still offline — keep queued */ });
            }));
        });
    }).catch(function () { /* no IndexedDB / open failed — nothing to flush */ });
}

self.addEventListener('sync', function (event) {
    if (event.tag === 'gm-outbox') { event.waitUntil(flushOutbox()); }
});

self.addEventListener('message', function (event) {
    if (event.data && event.data.type === 'flush-outbox') { event.waitUntil(flushOutbox()); }
});

// --- Web Push --------------------------------------------------------------
// The backend (WebPushService) sends a JSON payload { title, body, url?, tag? };
// clicking the notification focuses an existing matching tab or opens the URL.
self.addEventListener('push', function (event) {
    var data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = { body: event.data ? event.data.text() : '' }; }
    var title = data.title || 'Gestionale Muratori';
    var options = {
        body: data.body || '',
        icon: scoped('assets/icons/icon-192.png'),
        badge: scoped('assets/icons/icon-192.png'),
        tag: data.tag || undefined,
        renotify: !!data.tag,
        data: { url: data.url ? new URL(data.url, SCOPE).toString() : SCOPE },
        requireInteraction: !!data.requireInteraction
    };
    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    var target = (event.notification.data && event.notification.data.url) || SCOPE;
    event.waitUntil(
        self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (list) {
            for (var i = 0; i < list.length; i++) {
                if (list[i].url === target && 'focus' in list[i]) { return list[i].focus(); }
            }
            if (self.clients.openWindow) { return self.clients.openWindow(target); }
        })
    );
});
