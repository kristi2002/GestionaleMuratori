<?php
/**
 * Lead model (migration 028): create/count/status filter and convert-linking.
 * In-process; cleans up what it creates.
 */
declare(strict_types=1);

use App\Models\ClientModel;
use App\Models\LeadModel;

/** @var PDO $pdo */

T::section('Leads: model + status workflow');

$m         = new LeadModel();
$baseTotal = $m->countByStatus()['_total'];

$id1 = $m->create(['name' => 'LEADTEST A', 'email' => 'a@x.com', 'phone' => null, 'message' => 'Preventivo', 'source' => 'web', 'ip' => null]);
$id2 = $m->create(['name' => 'LEADTEST B', 'email' => null, 'phone' => '3330000000', 'message' => null, 'source' => 'web', 'ip' => null]);
T::equals($baseTotal + 2, $m->countByStatus()['_total'], 'two leads created');
T::ok($m->newCount() >= 2, 'new leads counted');

$m->setStatus($id1, 'contacted');
T::equals('contacted', (string) $m->find($id1)['status'], 'status updated');
T::ok($m->setStatus($id1, 'bogus') === false, 'invalid status rejected by the model');

T::ok(count(array_filter($m->all('contacted'), static fn ($r) => (int) $r['id'] === $id1)) === 1, 'all(contacted) includes the contacted lead');
T::ok(count(array_filter($m->all('new'), static fn ($r) => (int) $r['id'] === $id1)) === 0, 'all(new) excludes the contacted lead');

$clientId = (new ClientModel())->create(['name' => 'LEADTEST A', 'vat_or_tax_id' => null, 'email' => 'a@x.com', 'phone' => null, 'address' => null, 'notes' => null]);
$m->markConverted($id1, $clientId);
$row = $m->find($id1);
T::equals('converted', (string) $row['status'], 'lead marked converted');
T::equals($clientId, (int) $row['client_id'], 'lead linked to the created client');

// Teardown.
$m->delete($id1);
$m->delete($id2);
$pdo->exec('DELETE FROM clients WHERE id = ' . (int) $clientId);
T::equals($baseTotal, $m->countByStatus()['_total'], 'teardown restores the baseline');
