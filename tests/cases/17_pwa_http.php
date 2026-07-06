<?php
/**
 * HTTP end-to-end: offline PWA shell (v2 Phase 5). The manifest, service worker,
 * offline page and icons are served, and pages advertise the manifest.
 */
declare(strict_types=1);

/** @var string $baseUrl */

// ---------------------------------------------------------------------------
T::section('E2E: PWA shell');
$anon = new HttpClient($baseUrl);

$m = $anon->get('/manifest.webmanifest', ['json' => false]);
T::equals(200, $m['status'], 'manifest served');
T::ok(str_contains($m['body'], '"standalone"'), 'manifest declares standalone display');

$sw = $anon->get('/sw.js', ['json' => false]);
T::equals(200, $sw['status'], 'service worker served');
T::ok(str_contains($sw['body'], 'addEventListener'), 'service worker looks like a worker script');

T::equals(200, $anon->get('/offline.html', ['json' => false])['status'], 'offline fallback page served');
T::equals(200, $anon->get('/assets/icons/icon-192.png', ['json' => false])['status'], 'PWA icon served');

$login = $anon->get('/login', ['json' => false]);
T::ok(str_contains($login['body'], 'rel="manifest"'), 'pages link the web app manifest');
T::ok(str_contains($login['body'], 'theme-color'), 'pages set a theme color');
