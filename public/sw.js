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
var VERSION = 'gm-shell-v14';
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
