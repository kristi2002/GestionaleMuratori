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

// --- Per-user scoping (Phase 4) ----------------------------------------------
T::section('NotificationModel: per-user scoping');
$clientUid = (int) $pdo->query("SELECT id FROM users WHERE email = 'client1@gestionale.local'")->fetchColumn();
$otherUid  = (int) $pdo->query("SELECT id FROM users WHERE email = 'client2@gestionale.local'")->fetchColumn();
$gBefore   = $notif->unreadCount();
$notif->createIfAbsent(['type' => 'system', 'title' => 'Solo per il cliente', 'user_id' => $clientUid, 'dedup_key' => 'scope:test:' . $clientUid]);
T::equals(1, $notif->unreadCount($clientUid), 'the target user sees their own unread row');
T::equals($gBefore, $notif->unreadCount(), 'a user-scoped row never enters the admin/global feed');
T::equals(0, $notif->unreadCount($otherUid), 'a different user does not see it');
$scopedId = (int) $pdo->query("SELECT id FROM notifications WHERE user_id = {$clientUid} ORDER BY id DESC LIMIT 1")->fetchColumn();
T::ok($notif->markRead($scopedId, $otherUid) === false, 'a different user cannot mark it read (ownership)');
T::ok($notif->markRead($scopedId, $clientUid) === true, 'the owner marks it read');
T::equals(0, $notif->unreadCount($clientUid), 'owner unread cleared after mark-read');

// --- Notifications HTTP surface ----------------------------------------------
T::section('Notifications: HTTP RBAC');
$admin = new HttpClient($baseUrl);
$admin->login('admin@gestionale.local', 'password');
$page = $admin->get('/admin/notifications', ['json' => false]);
T::equals(200, $page['status'], 'admin can open the notifications page');
T::ok(str_contains($page['body'], 'Notifiche'), 'notifications page renders its title');

// worker2 (worker1's password is changed and not restored by case 10, so a
// worker1 login here would be silently anonymous — use worker2 like cases 12–18).
$worker = new HttpClient($baseUrl);
$worker->login('worker2@gestionale.local', 'password');
$blocked = $worker->get('/admin/notifications');
T::equals(403, $blocked['status'], 'authenticated worker is blocked from admin notifications (403)');

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

// --- Test-email route (Phase 2) ----------------------------------------------
T::section('Notifications: test-email route');
$workerTest = $worker->post('/admin/notifications/test-email');
T::equals(403, $workerTest['status'], 'worker is blocked from the test-email endpoint');
// Mail is disabled in the test env, so the admin gets a clean 422, never a 500.
$adminTest = $admin->post('/admin/notifications/test-email');
T::equals(422, $adminTest['status'], 'admin test-email reports mail-disabled (422, not a crash)');
T::ok(($adminTest['json']['ok'] ?? true) === false, 'test-email response is ok:false when mail is disabled');

// --- Client notification feed + event fan-out (Phase 4) ----------------------
T::section('Client notifications: RBAC + invoice event fan-out');
// A non-client cannot open the client feed.
T::equals(403, $worker->get('/client/notifications')['status'], 'worker cannot open the client feed');
// The client can open their own feed.
T::equals(200, $c1->get('/client/notifications', ['json' => false])['status'], 'client can open their own feed');

// Issuing an invoice for the client's project creates an in-app notification for them.
$projId  = (int) $pdo->query("SELECT id FROM projects WHERE client_id = {$cid} ORDER BY id LIMIT 1")->fetchColumn();
$before  = (int) $pdo->query("SELECT COUNT(*) FROM notifications n JOIN users u ON u.id = n.user_id WHERE u.email = 'client1@gestionale.local'")->fetchColumn();
$invResp = $admin->post('/admin/invoices', [
    'project_id' => $projId,
    'number'     => 'TEST-NOTIF-1',
    'issue_date' => '2026-07-16',
    'amount'     => '100',
    'status'     => 'issued',
]);
T::ok(($invResp['json']['ok'] ?? false) === true, 'admin issues an invoice for the client project');
$after = (int) $pdo->query("SELECT COUNT(*) FROM notifications n JOIN users u ON u.id = n.user_id WHERE u.email = 'client1@gestionale.local'")->fetchColumn();
T::ok($after > $before, 'issuing the invoice fanned out a client notification');
// A client cannot mark another client's notification (ownership via user scope).
$otherRow = (int) $pdo->query("SELECT n.id FROM notifications n JOIN users u ON u.id = n.user_id WHERE u.email = 'client1@gestionale.local' ORDER BY n.id DESC LIMIT 1")->fetchColumn();
T::ok($c2->post('/client/notifications/' . $otherRow . '/read')['status'] === 200, 'foreign mark-read returns ok (no-op, not an error)');
T::equals(0, (int) $pdo->query("SELECT is_read FROM notifications WHERE id = {$otherRow}")->fetchColumn(), 'foreign client did NOT actually mark it read');

// --- Client-visible invoices on the project page (Phase 4) -------------------
T::section('Client project page: read-only invoices');
$projPage = $c1->get('/client/projects/' . $projId, ['json' => false]);
T::equals(200, $projPage['status'], 'client opens their project page');
T::ok(str_contains($projPage['body'], 'TEST-NOTIF-1'), 'issued invoice is visible on the client project page');
// A draft invoice must never be shown to the client.
$admin->post('/admin/invoices', [
    'project_id' => $projId, 'number' => 'DRAFT-HIDDEN-1',
    'issue_date' => '2026-07-16', 'amount' => '50', 'status' => 'draft',
]);
$projPage2 = $c1->get('/client/projects/' . $projId, ['json' => false]);
T::ok(!str_contains($projPage2['body'], 'DRAFT-HIDDEN-1'), 'draft invoice is hidden from the client');

// --- Dispatch board + reassign (Phase 5) -------------------------------------
T::section('Dispatch board + worker reassignment');
T::equals(403, $worker->get('/admin/interventions/dispatch')['status'], 'worker cannot open the dispatch board');
T::equals(200, $admin->get('/admin/interventions/dispatch', ['json' => false])['status'], 'admin opens the dispatch board');

$ivId         = (int) $pdo->query("SELECT id FROM interventions ORDER BY id LIMIT 1")->fetchColumn();
$wId          = (int) $pdo->query("SELECT id FROM users WHERE role = 'worker' AND is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
$clientUserId = (int) $pdo->query("SELECT id FROM users WHERE role = 'client' ORDER BY id LIMIT 1")->fetchColumn();

$ra = $admin->post('/admin/interventions/' . $ivId . '/reassign', ['worker_id' => $wId]);
T::ok(($ra['json']['ok'] ?? false) === true, 'admin reassigns an intervention to a worker');
T::equals($wId, (int) $pdo->query("SELECT assigned_worker_id FROM interventions WHERE id = {$ivId}")->fetchColumn(), 'worker assignment persisted');
T::equals(422, $admin->post('/admin/interventions/' . $ivId . '/reassign', ['worker_id' => $clientUserId])['status'], 'cannot assign a non-worker user');
$admin->post('/admin/interventions/' . $ivId . '/reassign', ['worker_id' => 0]);
T::ok($pdo->query("SELECT assigned_worker_id FROM interventions WHERE id = {$ivId}")->fetchColumn() === null, 'worker_id 0 unassigns (NULL)');
T::equals(403, $worker->post('/admin/interventions/' . $ivId . '/reassign', ['worker_id' => $wId])['status'], 'worker cannot reassign');
