<?php
/**
 * Automation platform (scheduler + notifications), pagination, and client quote
 * self-service. Runs last so its data mutations (created notifications, an
 * accepted quote) can't perturb earlier e2e cases.
 */
declare(strict_types=1);

use App\Models\InterventionModel;
use App\Models\NotificationModel;
use App\Services\SchedulerService;

/** @var PDO $pdo */
/** @var string $baseUrl */

// --- Scheduler: idempotent alert generation ----------------------------------
T::section('Scheduler: notification generation');
$notif = new NotificationModel();

$run1 = (new SchedulerService())->run();
T::ok($run1['total'] > 0, 'first run creates notifications from seed data');
T::ok($run1['created']['compliance_expiry'] >= 2, 'compliance expiries detected (seed has expiring + expired)');
T::ok($run1['created']['intervention_overdue'] >= 1, 'overdue intervention detected');
T::equals($run1['total'], $notif->unreadCount(), 'all created notifications start unread');

$run2 = (new SchedulerService())->run();
T::equals(0, $run2['total'], 'second run is idempotent (no duplicates)');

T::section('NotificationModel: dedup + read state');
$k = 'test:unique:' . $run1['total'];
T::ok($notif->createIfAbsent(['type' => 'system', 'title' => 'Test', 'dedup_key' => $k]) === true, 'new dedup key inserts');
T::ok($notif->createIfAbsent(['type' => 'system', 'title' => 'Test', 'dedup_key' => $k]) === false, 'same dedup key is ignored');
$before = $notif->unreadCount();
T::ok($before >= 1, 'there are unread notifications');
$marked = $notif->markAllRead();
T::ok($marked >= $before, 'markAllRead clears every unread row');
T::equals(0, $notif->unreadCount(), 'no unread notifications after markAllRead');

// --- Notifications HTTP surface ----------------------------------------------
T::section('Notifications: HTTP RBAC');
$admin = new HttpClient($baseUrl);
$admin->login('admin@gestionale.local', 'password');
$page = $admin->get('/admin/notifications', ['json' => false]);
T::equals(200, $page['status'], 'admin can open the notifications page');
T::ok(str_contains($page['body'], 'Notifiche'), 'notifications page renders its title');

$worker = new HttpClient($baseUrl);
$worker->login('worker1@gestionale.local', 'password');
$blocked = $worker->get('/admin/notifications');
T::ok($blocked['status'] !== 200, 'worker is blocked from admin notifications');

// --- Pagination: model count + limit/offset ----------------------------------
T::section('Pagination: interventions count + window');
$im     = new InterventionModel();
$total  = $im->count([]);
$full   = $im->all([]);
T::equals($total, count($full), 'count() matches full result size');
if ($total >= 4) {
    $p1 = $im->all([], 2, 0);
    $p2 = $im->all([], 2, 2);
    T::ok(count($p1) === 2, 'first window returns the page size');
    $ids1 = array_map(static fn ($r) => (int) $r['id'], $p1);
    $ids2 = array_map(static fn ($r) => (int) $r['id'], $p2);
    T::ok(array_intersect($ids1, $ids2) === [], 'consecutive windows do not overlap');
} else {
    T::ok(true, 'not enough rows to window (skipped)');
}
$statusFilterCount = $im->count(['status' => 'completed']);
T::ok($statusFilterCount <= $total, 'filtered count() never exceeds total');

// --- Client quote self-service -----------------------------------------------
T::section('Client quotes: accept / reject ownership');
$cid     = (int) $pdo->query("SELECT client_id FROM users WHERE email = 'client1@gestionale.local'")->fetchColumn();
$quoteId = (int) $pdo->query("SELECT id FROM quotes WHERE client_id = {$cid} AND status = 'sent' ORDER BY id LIMIT 1")->fetchColumn();
T::ok($quoteId > 0, 'seed has a sent quote for client1');

$c1 = new HttpClient($baseUrl);
$c1->login('client1@gestionale.local', 'password');
$list = $c1->get('/client/quotes', ['json' => false]);
T::equals(200, $list['status'], 'client can open their quotes list');
$detail = $c1->get('/client/quotes/' . $quoteId, ['json' => false]);
T::equals(200, $detail['status'], 'client can open their quote detail');

// Another client cannot decide on someone else's quote.
$c2 = new HttpClient($baseUrl);
$c2->login('client2@gestionale.local', 'password');
$foreign = $c2->post('/client/quotes/' . $quoteId . '/reject');
T::ok($foreign['status'] === 422 || $foreign['status'] === 404, 'foreign client cannot reject the quote');
T::equals('sent', (string) $pdo->query("SELECT status FROM quotes WHERE id = {$quoteId}")->fetchColumn(), 'quote still sent after foreign attempt');

// The owner accepts it.
$accept = $c1->post('/client/quotes/' . $quoteId . '/accept');
T::ok(($accept['json']['ok'] ?? false) === true, 'owner accepts the quote');
T::equals('accepted', (string) $pdo->query("SELECT status FROM quotes WHERE id = {$quoteId}")->fetchColumn(), 'quote is now accepted');

// A second decision is rejected (no longer 'sent').
$again = $c1->post('/client/quotes/' . $quoteId . '/reject');
T::equals(422, $again['status'], 'cannot change an already-decided quote');
