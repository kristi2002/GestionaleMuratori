<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\ComplianceDocumentModel;
use App\Models\NotificationModel;
use App\Models\RecurringInterventionModel;
use App\Models\UserModel;
use App\Support\Config;
use App\Support\Database;
use App\Support\Lang;
use App\Support\Mailer;

/**
 * Scheduled automation: turns the data the platform already holds into proactive
 * alerts. Run daily by scripts/scheduler.php (cron). Every generator is idempotent
 * — it de-duplicates on NotificationModel's dedup_key — so re-running the same day
 * never produces duplicates. When e-mail is enabled (MAIL_ENABLED=true) a single
 * digest of the newly-created alerts is sent to the admins.
 *
 * Generators:
 *   - compliance_expiry     — DURC/POS/Patente a Crediti/... expiring soon or expired
 *   - quote_expired         — quotes past valid_until, auto-marked 'expired'
 *   - intervention_overdue  — open interventions whose scheduled_date has passed
 *   - low_stock             — active items at/below their reorder level
 */
final class SchedulerService
{
    private NotificationModel $notifications;

    public function __construct()
    {
        $this->notifications = new NotificationModel();
    }

    /**
     * @return array{created:array<string,int>,total:int,emailed:bool,recurring:int}
     */
    public function run(?string $today = null): array
    {
        $today = $today ?? date('Y-m-d');

        /** @var array<int,array{severity:string,title:string,body:?string}> $fresh */
        $fresh   = [];
        $created = [
            'compliance_expiry'    => $this->generateComplianceExpiry($today, $fresh),
            'quote_expired'        => $this->generateExpiredQuotes($today, $fresh),
            'intervention_overdue' => $this->generateOverdueInterventions($today, $fresh),
            'low_stock'            => $this->generateLowStock($today, $fresh),
        ];

        $total   = array_sum($created);
        $emailed = $total > 0 && $this->sendDigest($fresh, $today);
        if ($total > 0) {
            $this->pushAdmins();
        }

        // Materialise due recurring interventions (maintenance plans). Separate from
        // the notification total — these create interventions, not alerts.
        $recurring = $this->generateRecurringInterventions($today);

        return ['created' => $created, 'total' => $total, 'emailed' => $emailed, 'recurring' => $recurring];
    }

    /** @param array<int,array{severity:string,title:string,body:?string}> $fresh */
    private function generateComplianceExpiry(string $today, array &$fresh): int
    {
        $days  = (int) Config::get('scheduler.compliance_days', 30);
        $count = 0;
        foreach ((new ComplianceDocumentModel())->expiringSoon($days) as $doc) {
            $expired  = (string) $doc['expiry_date'] < $today;
            $docLabel = Lang::label('compliance_doc', (string) $doc['doc_type']);
            $subject  = (string) ($doc['subject_name'] ?? Lang::get('admin.compliance.the_company'));
            $title    = sprintf(
                Lang::get($expired ? 'notifications.compliance_expired' : 'notifications.compliance_expiring'),
                $docLabel
            );
            $body = sprintf(Lang::get('notifications.compliance_body'), $subject, (string) $doc['expiry_date']);

            $count += $this->push([
                'type'      => 'compliance_expiry',
                'severity'  => $expired ? 'danger' : 'warning',
                'title'     => $title,
                'body'      => $body,
                'link'      => '/admin/compliance?expiring=1',
                'dedup_key' => 'compliance:' . $doc['id'] . ':' . $doc['expiry_date'],
            ], $fresh);
        }
        return $count;
    }

    /** @param array<int,array{severity:string,title:string,body:?string}> $fresh */
    private function generateExpiredQuotes(string $today, array &$fresh): int
    {
        $pdo  = Database::pdo();
        $stmt = $pdo->prepare(
            "SELECT id, number, title FROM quotes
             WHERE status IN ('draft','sent') AND valid_until IS NOT NULL AND valid_until < ?"
        );
        $stmt->execute([$today]);
        $rows = $stmt->fetchAll();

        $update = $pdo->prepare("UPDATE quotes SET status = 'expired' WHERE id = ? AND status IN ('draft','sent')");
        $count  = 0;
        foreach ($rows as $q) {
            $update->execute([$q['id']]);
            $count += $this->push([
                'type'      => 'quote_expired',
                'severity'  => 'info',
                'title'     => sprintf(Lang::get('notifications.quote_expired'), (string) $q['number']),
                'body'      => sprintf(Lang::get('notifications.quote_expired_body'), (string) $q['title']),
                'link'      => '/admin/quotes',
                'dedup_key' => 'quote_expired:' . $q['id'],
            ], $fresh);
        }
        return $count;
    }

    /** @param array<int,array{severity:string,title:string,body:?string}> $fresh */
    private function generateOverdueInterventions(string $today, array &$fresh): int
    {
        $stmt = Database::pdo()->prepare(
            "SELECT i.id, i.title, i.scheduled_date, i.assigned_worker_id, p.name AS project_name
             FROM interventions i JOIN projects p ON p.id = i.project_id
             WHERE i.status IN ('pending','in_progress','on_hold')
               AND i.scheduled_date IS NOT NULL AND i.scheduled_date < ?"
        );
        $stmt->execute([$today]);

        $count = 0;
        foreach ($stmt->fetchAll() as $iv) {
            $title = sprintf(Lang::get('notifications.intervention_overdue'), (string) $iv['title']);
            $body  = sprintf(
                Lang::get('notifications.intervention_overdue_body'),
                (string) $iv['project_name'],
                (string) $iv['scheduled_date']
            );
            $count += $this->push([
                'type'      => 'intervention_overdue',
                'severity'  => 'warning',
                'title'     => $title,
                'body'      => $body,
                'link'      => '/admin/interventions/' . $iv['id'],
                'dedup_key' => 'intervention_overdue:' . $iv['id'] . ':' . $iv['scheduled_date'],
            ], $fresh);

            // Also alert the assigned worker on their own feed (and push their devices).
            if ($iv['assigned_worker_id'] !== null) {
                NotificationService::notifyUser((int) $iv['assigned_worker_id'], [
                    'type'      => 'intervention_overdue',
                    'severity'  => 'warning',
                    'title'     => $title,
                    'body'      => $body,
                    'link'      => '/worker/interventions/' . $iv['id'],
                    'dedup_key' => 'intervention_overdue:worker:' . $iv['id'] . ':' . $iv['scheduled_date'],
                ]);
            }
        }
        return $count;
    }

    /** @param array<int,array{severity:string,title:string,body:?string}> $fresh */
    private function generateLowStock(string $today, array &$fresh): int
    {
        $stmt = Database::pdo()->query(
            'SELECT id, name, qty_in_stock, reorder_level, unit FROM warehouse_items
             WHERE is_active = 1 AND reorder_level > 0 AND qty_in_stock <= reorder_level'
        );
        $month = substr($today, 0, 7); // re-alert at most once per calendar month per item
        $qty   = static fn ($v): string => rtrim(rtrim((string) $v, '0'), '.');

        $count = 0;
        foreach ($stmt->fetchAll() as $item) {
            $count += $this->push([
                'type'      => 'low_stock',
                'severity'  => (float) $item['qty_in_stock'] <= 0 ? 'danger' : 'warning',
                'title'     => sprintf(Lang::get('notifications.low_stock'), (string) $item['name']),
                'body'      => sprintf(
                    Lang::get('notifications.low_stock_body'),
                    $qty($item['qty_in_stock']),
                    Lang::label('units', (string) $item['unit']),
                    $qty($item['reorder_level'])
                ),
                'link'      => '/admin/warehouse/' . $item['id'],
                'dedup_key' => 'low_stock:' . $item['id'] . ':' . $month,
            ], $fresh);
        }
        return $count;
    }

    /**
     * Insert one notification; on success record it for the digest.
     *
     * @param array<string,mixed> $data
     * @param array<int,array{severity:string,title:string,body:?string}> $fresh
     */
    private function push(array $data, array &$fresh): int
    {
        if (!$this->notifications->createIfAbsent($data)) {
            return 0;
        }
        $fresh[] = [
            'severity' => (string) $data['severity'],
            'title'    => (string) $data['title'],
            'body'     => $data['body'] !== null ? (string) $data['body'] : null,
        ];
        return 1;
    }

    /**
     * Send a single digest of the freshly-created alerts to the admins. No-op when
     * mail is disabled or there is nothing new. Recipients: MAIL_DIGEST_RECIPIENTS
     * if set, otherwise every active admin's e-mail.
     *
     * @param array<int,array{severity:string,title:string,body:?string}> $fresh
     */
    private function sendDigest(array $fresh, string $today): bool
    {
        if ($fresh === [] || !Mailer::isEnabled()) {
            return false;
        }
        $recipients = $this->digestRecipients();
        if ($recipients === []) {
            return false;
        }

        $order = ['danger' => 0, 'warning' => 1, 'info' => 2];
        usort($fresh, static fn ($a, $b): int => ($order[$a['severity']] ?? 9) <=> ($order[$b['severity']] ?? 9));

        $rows = '';
        foreach ($fresh as $n) {
            $color = ['danger' => '#D33A2C', 'warning' => '#E07C10', 'info' => '#2C6E9B'][$n['severity']] ?? '#666';
            $rows .= '<tr><td style="padding:8px 12px;border-left:4px solid ' . $color . ';">'
                . '<strong>' . htmlspecialchars($n['title'], ENT_QUOTES) . '</strong>'
                . ($n['body'] !== null ? '<br><span style="color:#555">' . htmlspecialchars($n['body'], ENT_QUOTES) . '</span>' : '')
                . '</td></tr>';
        }
        $html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:620px">'
            . '<h2 style="color:#171A1F">' . htmlspecialchars(Lang::get('app_name'), ENT_QUOTES) . '</h2>'
            . '<p>' . htmlspecialchars(sprintf(Lang::get('notifications.digest_intro'), $today), ENT_QUOTES) . '</p>'
            . '<table style="border-collapse:collapse;width:100%">' . $rows . '</table>'
            . '<p style="color:#888;font-size:12px;margin-top:16px">'
            . htmlspecialchars(Lang::get('app_name'), ENT_QUOTES) . '</p></div>';

        return Mailer::send($recipients, sprintf(Lang::get('notifications.digest_subject'), $today), $html);
    }

    /**
     * Best-effort lock-screen push to every admin when new alerts were created. The
     * admin's service worker then fetches the freshest alert from /push/pending (the
     * global feed). No-op when push is unconfigured or no admin has subscribed.
     */
    private function pushAdmins(): void
    {
        $adminIds = [];
        foreach ((new UserModel())->listByRole('admin') as $admin) {
            $adminIds[] = (int) $admin['id'];
        }
        PushService::sendToUsers($adminIds);
    }

    /** Public entry (tests / targeted runs): only the recurring-intervention generation. */
    public function generateRecurring(?string $today = null): int
    {
        return $this->generateRecurringInterventions($today ?? date('Y-m-d'));
    }

    /**
     * Materialise real interventions from due recurring plans, advancing each plan's
     * next_run_date. Idempotent: advancing past today means a same-day re-run finds
     * nothing due. Catch-up is capped (60 occurrences/plan/run) as a runaway guard.
     * A plan is deactivated once its schedule passes its end_date.
     */
    private function generateRecurringInterventions(string $today): int
    {
        $model   = new RecurringInterventionModel();
        $service = new InterventionService();
        $created = 0;

        foreach ($model->due($today) as $rec) {
            $next  = new \DateTimeImmutable((string) $rec['next_run_date']);
            $end   = $rec['end_date'] !== null ? new \DateTimeImmutable((string) $rec['end_date']) : null;
            $limit = 0;

            while ($next->format('Y-m-d') <= $today && ($end === null || $next <= $end) && $limit < 60) {
                $service->create([
                    'project_id'           => (int) $rec['project_id'],
                    'assigned_worker_id'   => $rec['assigned_worker_id'] !== null ? (int) $rec['assigned_worker_id'] : null,
                    'title'                => (string) $rec['title'],
                    'description'          => $rec['description'] !== null ? (string) $rec['description'] : null,
                    'scheduled_date'       => $next->format('Y-m-d'),
                    'scheduled_start_time' => $rec['scheduled_start_time'],
                ], [], (int) $rec['created_by']);
                $created++;
                $next = $this->advanceDate($next, (string) $rec['frequency'], (int) $rec['interval_count']);
                $limit++;
            }

            $stillActive = $end === null || $next <= $end;
            $model->advance((int) $rec['id'], $next->format('Y-m-d'), $stillActive);
        }

        return $created;
    }

    private function advanceDate(\DateTimeImmutable $date, string $frequency, int $interval): \DateTimeImmutable
    {
        $unit = $frequency === 'monthly' ? 'months' : 'weeks';
        return $date->modify('+' . max(1, $interval) . ' ' . $unit);
    }

    /** @return array<int,string> */
    private function digestRecipients(): array
    {
        $configured = trim((string) Config::get('mail.digest_recipients', ''));
        if ($configured !== '') {
            return Mailer::normalizeRecipients(explode(',', $configured));
        }
        $emails = [];
        foreach ((new UserModel())->listByRole('admin') as $admin) {
            if (!empty($admin['email'])) {
                $emails[] = (string) $admin['email'];
            }
        }
        return Mailer::normalizeRecipients($emails);
    }
}
