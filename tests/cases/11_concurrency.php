<?php
/**
 * §9 acceptance: "Two interventions completing on the same item don't corrupt
 * stock (transaction + FOR UPDATE)." Fires the two completion POSTs truly in
 * parallel with curl_multi, then verifies the cache still equals the ledger
 * and the net effect matches both qty_used values.
 * Also: the report PDF must actually embed the uploaded photos (§5/§9).
 */
declare(strict_types=1);

/** @var PDO $pdo */
/** @var string $baseUrl */

T::section('E2E: concurrent completions on the same item');

$stockOf = static function (int $itemId) use ($pdo): float {
    $stmt = $pdo->prepare('SELECT qty_in_stock FROM warehouse_items WHERE id = ?');
    $stmt->execute([$itemId]);
    return (float) $stmt->fetchColumn();
};
$ledgerSum = static function (int $itemId) use ($pdo): float {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(CASE
            WHEN type IN ('in','release') THEN qty
            WHEN type = 'reserve' THEN -qty
            WHEN type = 'adjustment' THEN qty
            ELSE 0 END), 0)
         FROM stock_movements WHERE item_id = ?"
    );
    $stmt->execute([$itemId]);
    return (float) $stmt->fetchColumn();
};

$admin = new HttpClient($baseUrl);
$admin->login('admin@gestionale.local', 'password');

// Two fresh workers, one intervention each, same warehouse item (id 3, plenty of stock).
$workers = [];
foreach ([1, 2] as $n) {
    $r = $admin->post('/admin/users', [
        'name' => "Concurrent W{$n}", 'email' => "cw{$n}@test.local",
        'role' => 'worker', 'password' => 'Password123',
    ]);
    $workers[$n] = (int) ($r['json']['data']['id'] ?? 0);
}

$ITEM = 3;
$before = $stockOf($ITEM);
$ivs = [];
foreach ([1, 2] as $n) {
    $r = $admin->post('/admin/interventions', [
        'project_id' => 1, 'assigned_worker_id' => $workers[$n], 'title' => "Concurrente {$n}",
        'scheduled_date' => date('Y-m-d'),
        'item_id' => [$ITEM], 'qty_planned' => ['10'],
    ]);
    $ivs[$n] = (int) ($r['json']['data']['id'] ?? 0);
}
T::equals($before - 20, $stockOf($ITEM), 'both reservations applied');

// Prepare both interventions: session per worker, start, after photo.
$png = sys_get_temp_dir() . '/gm-conc.png';
$im = imagecreatetruecolor(80, 60);
imagepng($im, $png);
imagedestroy($im);

$sessions = [];
$matIds   = [];
foreach ([1, 2] as $n) {
    $w = new HttpClient($baseUrl);
    $w->login("cw{$n}@test.local", 'Password123');
    $w->post("/worker/interventions/{$ivs[$n]}/status", ['to_status' => 'in_progress']);
    $w->request('POST', "/worker/interventions/{$ivs[$n]}/photos", [
        'multipart' => ['photo' => new CURLFile($png, 'image/png', 'p.png'), 'type' => 'after'],
    ]);
    $stmt = $pdo->prepare('SELECT id FROM intervention_materials WHERE intervention_id = ?');
    $stmt->execute([$ivs[$n]]);
    $matIds[$n] = (int) $stmt->fetchColumn();
    $sessions[$n] = $w;
}
@unlink($png);

// Fire the two completions at the same instant (qty_used 7 and 4).
$multi = curl_multi_init();
$handles = [];
foreach ([1 => '7', 2 => '4'] as $n => $qty) {
    $h = curl_init($baseUrl . "/worker/interventions/{$ivs[$n]}/complete");
    curl_setopt_array($h, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(["qty_used[{$matIds[$n]}]" => $qty]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIE         => '', // set below from the session handle's jar
        CURLOPT_HTTPHEADER     => [
            'X-Requested-With: XMLHttpRequest',
            'X-CSRF-Token: ' . $sessions[$n]->csrf,
        ],
    ]);
    // Reuse the session cookie: extract it from the logged-in handle's jar.
    $jar = $sessions[$n]->cookieHeader();
    curl_setopt($h, CURLOPT_COOKIE, $jar);
    curl_multi_add_handle($multi, $h);
    $handles[$n] = $h;
}

do {
    $status = curl_multi_exec($multi, $running);
    if ($running) {
        curl_multi_select($multi);
    }
} while ($running && $status === CURLM_OK);

foreach ($handles as $n => $h) {
    $body = (string) curl_multi_getcontent($h);
    $code = (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE);
    T::equals(200, $code, "concurrent completion {$n} succeeded");
    curl_multi_remove_handle($multi, $h);
    curl_close($h);
}
curl_multi_close($multi);

// Net effect: -7 and -4 => before - 11; cache must equal ledger.
T::equals($before - 11, $stockOf($ITEM), 'stock reflects both completions exactly (no lost update)');
T::equals($ledgerSum($ITEM), $stockOf($ITEM), 'cache still equals ledger after the race');
foreach ([1, 2] as $n) {
    $stmt = $pdo->prepare('SELECT status FROM interventions WHERE id = ?');
    $stmt->execute([$ivs[$n]]);
    T::equals('completed', $stmt->fetchColumn(), "intervention {$n} completed");
}

// ---------------------------------------------------------------------------
T::section('E2E: concurrent transfers on the same item (no lost update)');

// Fresh item stocked with 100 at the warehouse via the admin API.
$r = $admin->post('/admin/warehouse', ['name' => 'Trasfer Race', 'sku' => 'TR-RACE', 'unit' => 'pcs', 'reorder_level' => '0']);
$xItem = (int) ($r['json']['data']['id'] ?? 0);
T::ok($xItem > 0, 'race item created');
$admin->post("/admin/warehouse/{$xItem}/movement", ['type' => 'in', 'qty' => '100', 'note' => 'seed race']);
$xSite = (int) $pdo->query("SELECT id FROM stock_locations WHERE project_id = 1 AND kind = 'site' LIMIT 1")->fetchColumn();

// Fire two warehouse->site transfers of 30 at the same instant.
$multiT   = curl_multi_init();
$handlesT = [];
foreach ([1, 2] as $n) {
    $h = curl_init($baseUrl . "/admin/warehouse/{$xItem}/transfer");
    curl_setopt_array($h, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query(['from_location_id' => 1, 'to_location_id' => $xSite, 'qty' => '30']),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['X-Requested-With: XMLHttpRequest', 'X-CSRF-Token: ' . $admin->csrf],
        CURLOPT_COOKIE         => $admin->cookieHeader(),
    ]);
    curl_multi_add_handle($multiT, $h);
    $handlesT[$n] = $h;
}
do {
    $status = curl_multi_exec($multiT, $running);
    if ($running) {
        curl_multi_select($multiT);
    }
} while ($running && $status === CURLM_OK);

$okT = 0;
foreach ($handlesT as $n => $h) {
    if ((int) curl_getinfo($h, CURLINFO_RESPONSE_CODE) === 200) {
        $okT++;
    }
    curl_multi_remove_handle($multiT, $h);
    curl_close($h);
}
curl_multi_close($multiT);
T::equals(2, $okT, 'both concurrent transfers returned 200');

$whQty   = (float) $pdo->query("SELECT qty FROM stock_balances WHERE item_id = {$xItem} AND location_id = 1")->fetchColumn();
$siteQty = (float) $pdo->query("SELECT qty FROM stock_balances WHERE item_id = {$xItem} AND location_id = {$xSite}")->fetchColumn();
T::equals(40.0, $whQty, 'warehouse balance = 100 - 30 - 30 (no lost update)');
T::equals(60.0, $siteQty, 'site balance = 30 + 30');
$fullX = (float) $pdo->query(
    "SELECT COALESCE(SUM(CASE
        WHEN type IN ('in','release','transfer_in') THEN qty
        WHEN type IN ('reserve','transfer_out') THEN -qty
        WHEN type = 'adjustment' THEN qty ELSE 0 END), 0)
     FROM stock_movements WHERE item_id = {$xItem}"
)->fetchColumn();
$sumX = (float) $pdo->query("SELECT COALESCE(SUM(qty), 0) FROM stock_balances WHERE item_id = {$xItem}")->fetchColumn();
T::equals($fullX, $sumX, 'sum of balances == full ledger after the transfer race');

// ---------------------------------------------------------------------------
T::section('E2E: PDF embeds photos');
$r = $admin->get('/admin/projects/1/report/pdf', ['json' => false]);
T::equals(200, $r['status'], 'project 1 PDF downloads');
T::ok(str_contains($r['body'], '/XObject'), 'PDF contains image XObjects');
T::ok(str_contains($r['body'], '/DCTDecode') || str_contains($r['body'], '/FlateDecode'), 'PDF contains encoded image data');
T::ok(strlen($r['body']) > 20000, 'PDF has substantial content (photos embedded)');
