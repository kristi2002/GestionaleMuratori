<?php
/**
 * Database-backed session handler: write/read/update/destroy round-trip and gc of
 * stale rows. (The HTTP login cases exercise it end-to-end via the dev server,
 * which runs under cli-server and so uses this handler.)
 */
declare(strict_types=1);

use App\Support\DatabaseSessionHandler;

/** @var PDO $pdo */

T::section('Sessions: DB handler round-trip');

$h   = new DatabaseSessionHandler();
$sid = 'testsid0000000001';

T::ok($h->write($sid, 'user|i:1;'), 'write succeeds');
T::equals('user|i:1;', $h->read($sid), 'read returns the payload');
T::ok($h->write($sid, 'user|i:2;'), 'overwrite succeeds');
T::equals('user|i:2;', $h->read($sid), 'read returns the updated payload');
T::ok($h->destroy($sid), 'destroy succeeds');
T::equals('', $h->read($sid), 'read after destroy is empty');
T::equals('', $h->read('does_not_exist'), 'unknown id reads empty');

T::section('Sessions: gc prunes stale rows');

$h->write('stale_sid_0001', 'x');
$pdo->exec("UPDATE sessions SET last_activity = 1 WHERE id = 'stale_sid_0001'");
$h->write('fresh_sid_0001', 'y');

$removed = $h->gc(3600);
T::ok($removed !== false && $removed >= 1, 'gc reports removed rows');
T::equals('', $h->read('stale_sid_0001'), 'stale session was pruned');
T::equals('y', $h->read('fresh_sid_0001'), 'fresh session survived gc');
