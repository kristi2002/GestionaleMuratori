<?php
/**
 * Scheduled automation runner — run daily via cron.
 *
 *   php scripts/scheduler.php
 *
 * Generates in-app notifications (and, when MAIL_ENABLED=true, an e-mail digest)
 * for: compliance-document expiries, quotes past their validity (auto-expired),
 * overdue interventions, and low stock. Idempotent — safe to run repeatedly; it
 * de-duplicates on each notification's dedup_key.
 *
 * Cron example (02:15 every day), see docs/DEPLOYMENT.md:
 *   15 2 * * *  cd /opt/gestionale && php scripts/scheduler.php >> /var/log/gestionale-scheduler.log 2>&1
 * Docker/Coolify: run the same command inside the app container.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Services\SchedulerService;
use App\Support\Lang;

Lang::load();

try {
    $result = (new SchedulerService())->run();
} catch (\Throwable $e) {
    fwrite(STDERR, '[scheduler] ERRORE: ' . $e->getMessage() . "\n");
    exit(1);
}

$stamp = date('Y-m-d H:i:s');
echo "[$stamp] scheduler: {$result['total']} nuove notifiche\n";
foreach ($result['created'] as $type => $n) {
    printf("  %-22s %d\n", $type, $n);
}
echo '  email digest: ' . ($result['emailed'] ? 'inviata' : 'non inviata (disabilitata o nulla da inviare)') . "\n";

exit(0);
