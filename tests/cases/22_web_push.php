<?php
/**
 * Web Push (VAPID) — Unit: dependency-free VAPID crypto (openssl only). A signed
 * ES256 JWT verifies against the key, the application-server key is a valid EC
 * point, and DER->raw signature conversion is correct. (The /push/* endpoint e2e
 * lives in 17_pwa_http.php, which runs in the HTTP phase.)
 */
declare(strict_types=1);

use App\Support\WebPush;

// A throwaway P-256 key — a fixture only, never used to send a real push. Signing
// and reading a PEM need no openssl.cnf, so this runs identically on CI and Windows.
$TEST_PEM = "-----BEGIN PRIVATE KEY-----\n"
    . "MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgOkBzD0T56Zqtz5g4\n"
    . "VjBUH3NRqba6m1xUPKESQMPI5q2hRANCAAR23611EP7/X+yYscU+XgfUuJFXncvJ\n"
    . "hr6JTuOxfItI+f9g/t5MASxHB2zJuIsGzxViJOky2MBNpziBjw8PGFfZ\n"
    . "-----END PRIVATE KEY-----\n";

$b64urld = static fn (string $s): string =>
    (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));

// ---------------------------------------------------------------------------
T::section('Unit: WebPush VAPID crypto');

$appKey = WebPush::publicKeyFromPem($TEST_PEM);
$point  = $b64urld($appKey);
T::equals(65, strlen($point), 'application server key is a 65-byte EC point');
T::equals(0x04, ord($point[0]), 'EC point is uncompressed (0x04 prefix)');
T::ok(strpos($appKey, '=') === false && strpos($appKey, '+') === false && strpos($appKey, '/') === false,
    'public key is unpadded base64url');

$exp = time() + 3600;
$jwt = WebPush::signJwt($TEST_PEM, 'https://fcm.googleapis.com', 'mailto:a@b.c', $exp);
$parts = explode('.', $jwt);
T::equals(3, count($parts), 'JWT has three dot-separated parts');
T::equals(['typ' => 'JWT', 'alg' => 'ES256'], json_decode($b64urld($parts[0]), true), 'JWT header is ES256');
$claims = json_decode($b64urld($parts[1]), true);
T::equals('https://fcm.googleapis.com', $claims['aud'], 'JWT aud is the endpoint origin');
T::equals('mailto:a@b.c', $claims['sub'], 'JWT sub is the configured subject');
T::equals($exp, $claims['exp'], 'JWT exp preserved');

$rawSig = $b64urld($parts[2]);
T::equals(64, strlen($rawSig), 'ES256 signature is raw 64-byte R||S');

// Rebuild the DER form and verify the signature against the derived public key.
$toDerInt = static function (string $v): string {
    $v = ltrim($v, "\0");
    if ($v === '' || (ord($v[0]) & 0x80)) { $v = "\0" . $v; }
    return "\x02" . chr(strlen($v)) . $v;
};
$seq = $toDerInt(substr($rawSig, 0, 32)) . $toDerInt(substr($rawSig, 32));
$der = "\x30" . chr(strlen($seq)) . $seq;
$pubPem = openssl_pkey_get_details(openssl_pkey_get_private($TEST_PEM))['key'];
T::equals(1, openssl_verify($parts[0] . '.' . $parts[1], $der, $pubPem, OPENSSL_ALGO_SHA256),
    'signed JWT verifies against the public key');

// derToRaw on a crafted DER with short (sign-padded) integers left-pads to 32 bytes.
$craft = "\x30\x08\x02\x01\x05\x02\x03\x00\x80\x01"; // r=0x05, s=0x008001
$raw   = WebPush::derToRaw($craft);
T::equals(64, strlen($raw), 'derToRaw always yields 64 bytes');
T::equals(5, ord($raw[31]), 'r right-aligned in first 32 bytes');
T::equals(0x80, ord($raw[62]), 's high byte placed correctly');
T::equals(0x01, ord($raw[63]), 's low byte placed correctly');

T::ok(!WebPush::isEnabled(), 'push disabled by default in tests (no VAPID_SUBJECT)');
