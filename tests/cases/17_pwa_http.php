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

// ---------------------------------------------------------------------------
// Web Push subscription endpoints (App\Controllers\PushController).
T::section('E2E: Web Push endpoints');
/** @var PDO $pdo */

T::equals(302, $anon->get('/push/public-key', ['json' => false])['status'],
    'anonymous /push/public-key redirects to login');

// worker2 (case 10 changes worker1's password and does not restore it).
$pushWorker = new HttpClient($baseUrl);
T::equals(200, $pushWorker->login('worker2@gestionale.local', 'password')['status'], 'worker2 login ok');

$pk = $pushWorker->get('/push/public-key');
T::ok(($pk['json']['ok'] ?? false) === true, 'public-key endpoint returns ok');
T::ok(($pk['json']['data']['enabled'] ?? null) === false, 'push disabled when no VAPID subject configured');
T::ok(array_key_exists('key', $pk['json']['data'] ?? []), 'response carries a key field (may be empty)');

// Validation: non-https or key-less subscriptions are rejected.
T::equals(422, $pushWorker->post('/push/subscribe', ['endpoint' => 'http://insecure/x', 'p256dh' => 'a', 'auth' => 'b'])['status'],
    'non-https endpoint rejected');
T::equals(422, $pushWorker->post('/push/subscribe', ['endpoint' => 'https://push.example.com/e/1'])['status'],
    'missing keys rejected');

// Subscribe -> row stored against caller -> re-subscribe upserts -> unsubscribe.
$pushEndpoint = 'https://fcm.googleapis.com/fcm/send/test-' . bin2hex(random_bytes(6));
T::ok(($pushWorker->post('/push/subscribe', ['endpoint' => $pushEndpoint, 'p256dh' => 'BPtestkey', 'auth' => 'authsecret'])['json']['ok'] ?? false) === true,
    'valid subscription accepted');
$ownerId  = $pdo->query("SELECT user_id FROM push_subscriptions WHERE endpoint = " . $pdo->quote($pushEndpoint))->fetchColumn();
$workerId = (int) $pdo->query("SELECT id FROM users WHERE email='worker2@gestionale.local'")->fetchColumn();
T::equals($workerId, (int) $ownerId, 'subscription stored against the caller');

$pushWorker->post('/push/subscribe', ['endpoint' => $pushEndpoint, 'p256dh' => 'BPtestkey2', 'auth' => 'authsecret2']);
T::equals(1, (int) $pdo->query("SELECT COUNT(*) FROM push_subscriptions WHERE endpoint = " . $pdo->quote($pushEndpoint))->fetchColumn(),
    're-subscribe upserts rather than duplicating');

T::ok(($pushWorker->post('/push/unsubscribe', ['endpoint' => $pushEndpoint])['json']['ok'] ?? false) === true, 'unsubscribe ok');
T::equals(0, (int) $pdo->query("SELECT COUNT(*) FROM push_subscriptions WHERE endpoint = " . $pdo->quote($pushEndpoint))->fetchColumn(),
    'subscription removed on unsubscribe');

T::ok(($pushWorker->get('/push/pending')['json']['ok'] ?? false) === true, 'pending endpoint returns ok');
