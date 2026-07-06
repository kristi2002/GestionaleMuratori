<?php
/**
 * Seed script.
 *   php database/seed.php
 *
 * Idempotent: truncates all data tables, then inserts a known fixture set:
 *   1 admin, 2 workers, 2 clients (+2 client logins), 5 projects,
 *   10 warehouse items, and sample interventions.
 *
 * Stock integrity: every warehouse item receives one `in` stock movement equal
 * to its starting quantity, so qty_in_stock == SUM(ledger) from the start
 * (acceptance criterion §9). Reservation/commit movements are produced by the
 * application logic in later phases, not pre-baked here.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Support\Database;

$pdo = Database::pdo();
$pass = password_hash('password', PASSWORD_DEFAULT);

// All worker-app testing relies on "today"; compute it in PHP for clarity.
$today = (new DateTimeImmutable('today'))->format('Y-m-d');

$dataTables = [
    'photos', 'stock_balances', 'stock_movements',
    'compliance_documents', 'sal_lines', 'sal_documents',
    'daily_log_equipment', 'daily_logs', 'equipment', 'site_attendance',
    'project_subcontractors', 'intervention_materials', 'intervention_status_history',
    'interventions', 'warehouse_items', 'stock_locations', 'projects',
    'users', 'subcontractors', 'clients',
];

// --- Reset (FK-safe) ---------------------------------------------------------
// Done OUTSIDE the transaction: TRUNCATE causes an implicit commit in MySQL,
// so it cannot live inside the insert transaction below.
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ($dataTables as $table) {
    $pdo->exec("TRUNCATE TABLE {$table}");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

try {
    $pdo->beginTransaction();

    // --- Clients (companies) -------------------------------------------------
    $clientStmt = $pdo->prepare(
        'INSERT INTO clients (name, vat_or_tax_id, email, phone, address, notes)
         VALUES (:name, :vat, :email, :phone, :address, :notes)'
    );
    $clients = [
        ['Edilizia Rossi S.r.l.', 'IT01234567890', 'info@ediliziarossi.it', '+39 02 1234567', 'Via Milano 10, Milano', 'Cliente storico.'],
        ['Costruzioni Bianchi S.p.A.', 'IT09876543210', 'contatti@costruzionibianchi.it', '+39 011 7654321', 'Corso Torino 45, Torino', null],
    ];
    $clientIds = [];
    foreach ($clients as $c) {
        $clientStmt->execute([
            ':name' => $c[0], ':vat' => $c[1], ':email' => $c[2],
            ':phone' => $c[3], ':address' => $c[4], ':notes' => $c[5],
        ]);
        $clientIds[] = (int) $pdo->lastInsertId();
    }

    // --- Subcontractors (companies working under the main contractor) --------
    $subStmt = $pdo->prepare(
        'INSERT INTO subcontractors (name, vat_or_tax_id, email, phone, notes, is_active)
         VALUES (:name, :vat, :email, :phone, :notes, 1)'
    );
    $subcontractors = [
        ['Impianti Marche S.r.l.', 'IT02223334445', 'info@impiantimarche.it', '+39 071 998877', 'Impianti elettrici e idraulici.'],
    ];
    $subcontractorIds = [];
    foreach ($subcontractors as $s) {
        $subStmt->execute([
            ':name' => $s[0], ':vat' => $s[1], ':email' => $s[2],
            ':phone' => $s[3], ':notes' => $s[4],
        ]);
        $subcontractorIds[] = (int) $pdo->lastInsertId();
    }

    // --- Users ---------------------------------------------------------------
    $userStmt = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role, client_id, subcontractor_id, is_active)
         VALUES (:name, :email, :hash, :role, :client_id, :subcontractor_id, 1)'
    );
    // [name, email, role, client_id, subcontractor_id]
    $users = [
        ['Mario Amministratore', 'admin@gestionale.local', 'admin', null, null],
        ['Luca Operaio',         'worker1@gestionale.local', 'worker', null, null],
        ['Giuseppe Muratore',    'worker2@gestionale.local', 'worker', null, null],
        ['Referente Rossi',      'client1@gestionale.local', 'client', $clientIds[0], null],
        ['Referente Bianchi',    'client2@gestionale.local', 'client', $clientIds[1], null],
        ['Referente Impianti Marche', 'sub1@gestionale.local', 'subcontractor', null, $subcontractorIds[0]],
    ];
    $userIds = [];
    foreach ($users as $u) {
        $userStmt->execute([
            ':name' => $u[0], ':email' => $u[1], ':hash' => $pass,
            ':role' => $u[2], ':client_id' => $u[3], ':subcontractor_id' => $u[4],
        ]);
        $userIds[$u[1]] = (int) $pdo->lastInsertId();
    }
    $adminId   = $userIds['admin@gestionale.local'];
    $worker1Id = $userIds['worker1@gestionale.local'];
    $worker2Id = $userIds['worker2@gestionale.local'];

    // --- Projects ------------------------------------------------------------
    $projectStmt = $pdo->prepare(
        'INSERT INTO projects (client_id, name, location, lat, lng, start_date, end_date, invoice_reference, status)
         VALUES (:client_id, :name, :location, :lat, :lng, :start, :end, :invoice, :status)'
    );
    // [client_id, name, location, lat, lng, start, end, invoice, status] — coords enable
    // the Giornale dei Lavori weather auto-fill (Open-Meteo).
    $projects = [
        [$clientIds[0], 'Ristrutturazione Villa Rossi', 'Milano',  '45.4642000', '9.1900000', '2026-05-01', null,         'FATT-2026-001', 'active'],
        [$clientIds[0], 'Nuovo Capannone Rossi',        'Monza',   '45.5845000', '9.2744000', '2026-06-10', null,         'FATT-2026-014', 'active'],
        [$clientIds[1], 'Condominio Via Bianchi 12',    'Torino',  '45.0703000', '7.6869000', '2026-04-15', null,         'FATT-2026-007', 'active'],
        [$clientIds[1], 'Ufficio Bianchi Centro',       'Torino',  '45.0703000', '7.6869000', '2026-03-01', null,         'FATT-2026-003', 'on_hold'],
        [$clientIds[1], 'Magazzino Bianchi',            'Novara',  '45.4469000', '8.6222000', '2026-01-10', '2026-04-30', 'FATT-2026-002', 'closed'],
    ];
    $projectIds = [];
    foreach ($projects as $p) {
        $projectStmt->execute([
            ':client_id' => $p[0], ':name' => $p[1], ':location' => $p[2],
            ':lat' => $p[3], ':lng' => $p[4],
            ':start' => $p[5], ':end' => $p[6], ':invoice' => $p[7], ':status' => $p[8],
        ]);
        $projectIds[] = (int) $pdo->lastInsertId();
    }

    // --- Equipment catalog (Giornale dei Lavori) -----------------------------
    $equipStmt = $pdo->prepare('INSERT INTO equipment (name, is_active) VALUES (:name, 1)');
    foreach (['Betoniera', 'Gru a torre', 'Escavatore', 'Ponteggio', 'Autocarro'] as $equipName) {
        $equipStmt->execute([':name' => $equipName]);
    }

    // --- Stock locations -----------------------------------------------------
    // Default main warehouse is id=1 (the implicit location of every 'in' movement
    // below and the balance warehouse_items.qty_in_stock tracks). Each project also
    // gets its own site location so material can be transferred warehouse->cantiere.
    $pdo->prepare(
        "INSERT INTO stock_locations (id, name, kind, project_id, is_active)
         VALUES (1, 'Magazzino Centrale', 'warehouse', NULL, 1)"
    )->execute();
    $siteLocStmt = $pdo->prepare(
        "INSERT INTO stock_locations (name, kind, project_id, is_active)
         VALUES (:name, 'site', :project_id, 1)"
    );
    $siteLocationIds = [];
    foreach ($projectIds as $idx => $projectId) {
        $siteLocStmt->execute([':name' => 'Cantiere: ' . $projects[$idx][1], ':project_id' => $projectId]);
        $siteLocationIds[$projectId] = (int) $pdo->lastInsertId();
    }

    // --- Subcontractor assignments (M:N) -------------------------------------
    $psStmt = $pdo->prepare(
        'INSERT INTO project_subcontractors (project_id, subcontractor_id) VALUES (:project_id, :subcontractor_id)'
    );
    $psStmt->execute([':project_id' => $projectIds[0], ':subcontractor_id' => $subcontractorIds[0]]);

    // --- Warehouse items (+ initial `in` movement keeping the ledger honest) -
    $itemStmt = $pdo->prepare(
        'INSERT INTO warehouse_items (name, sku, unit, qty_in_stock, reorder_level, unit_cost, is_active)
         VALUES (:name, :sku, :unit, :qty, :reorder, :unit_cost, 1)'
    );
    $moveStmt = $pdo->prepare(
        'INSERT INTO stock_movements (item_id, type, qty, intervention_id, user_id, note)
         VALUES (:item_id, :type, :qty, NULL, :user_id, :note)'
    );
    // [name, sku, unit, qty_in_stock, reorder_level, unit_cost]
    $items = [
        ['Cemento Portland 25kg', 'CEM-25',  'pcs', '200.000', '40.000',  '6.5000'],
        ['Sabbia fine',           'SAB-01',  'kg',  '5000.000','500.000', '0.0450'],
        ['Mattoni forati 8x25x25','MAT-825', 'pcs', '10000.000','1000.000','0.4800'],
        ['Calce idraulica',       'CAL-IDR', 'kg',  '800.000', '100.000', '0.3200'],
        ['Tondino acciaio 12mm',  'ACC-12',  'm',   '1500.000','200.000', '1.1500'],
        ['Piastrelle gres 60x60', 'GRE-6060','box', '300.000', '30.000',  '24.9000'],
        ['Colla per piastrelle',  'COL-PIA', 'kg',  '600.000', '80.000',  '0.8500'],
        ['Tubo PVC 100mm',        'PVC-100', 'm',   '400.000', '50.000',  '3.4000'],
        ['Idropittura bianca',    'VER-IDR', 'l',   '250.000', '40.000',  '2.7000'],
        ['Guanti da lavoro',      'DPI-GNT', 'pcs', '500.000', '50.000',  '1.2000'],
    ];
    $itemIds = [];
    foreach ($items as $it) {
        $itemStmt->execute([
            ':name' => $it[0], ':sku' => $it[1], ':unit' => $it[2],
            ':qty' => $it[3], ':reorder' => $it[4], ':unit_cost' => $it[5],
        ]);
        $itemId = (int) $pdo->lastInsertId();
        $itemIds[] = $itemId;
        // Initial stock lands in the main warehouse (location_id defaults to 1).
        $moveStmt->execute([
            ':item_id' => $itemId, ':type' => 'in', ':qty' => $it[3],
            ':user_id' => $adminId, ':note' => 'Giacenza iniziale',
        ]);
    }

    // --- Per-location balance cache (mirror of the ledger, at the warehouse) --
    $pdo->exec(
        "INSERT INTO stock_balances (item_id, location_id, qty)
         SELECT item_id, location_id, COALESCE(SUM(CASE
                WHEN type IN ('in', 'release', 'transfer_in') THEN qty
                WHEN type IN ('reserve', 'transfer_out') THEN -qty
                WHEN type = 'adjustment' THEN qty
                ELSE 0
            END), 0)
         FROM stock_movements
         GROUP BY item_id, location_id"
    );

    // --- Interventions -------------------------------------------------------
    $intStmt = $pdo->prepare(
        'INSERT INTO interventions
            (project_id, assigned_worker_id, title, description, scheduled_date,
             scheduled_start_time, status, started_at, completed_at, completion_notes)
         VALUES
            (:project_id, :worker_id, :title, :description, :scheduled_date,
             :start_time, :status, :started_at, :completed_at, :completion_notes)'
    );
    // [project_idx, worker_id, title, scheduled_date, start_time, status, started_at, completed_at]
    $interventions = [
        [0, $worker1Id, 'Sopralluogo e demolizioni',   $today,        '08:00:00', 'in_progress', $today . ' 08:05:00', null],
        [0, $worker1Id, 'Posa nuovo massetto',          $today,        '13:00:00', 'pending',     null, null],
        [1, $worker2Id, 'Getto fondazioni',             $today,        '07:30:00', 'pending',     null, null],
        [2, $worker2Id, 'Rifacimento facciata - lotto 1','2026-06-20', '09:00:00', 'completed',   '2026-06-20 09:10:00', '2026-06-20 16:30:00'],
        [2, $worker1Id, 'Tinteggiatura vani scala',     '2026-07-05', '08:30:00', 'on_hold',     '2026-07-05 08:35:00', null],
        [3, null,       'Sostituzione impianto - preventivo', null,    null,       'pending',     null, null],
    ];
    $interventionIds = [];
    foreach ($interventions as $iv) {
        $intStmt->execute([
            ':project_id'   => $projectIds[$iv[0]],
            ':worker_id'    => $iv[1],
            ':title'        => $iv[2],
            ':description'  => 'Intervento di esempio generato dal seed.',
            ':scheduled_date' => $iv[3],
            ':start_time'   => $iv[4],
            ':status'       => $iv[5],
            ':started_at'   => $iv[6],
            ':completed_at' => $iv[7],
            ':completion_notes' => $iv[5] === 'completed' ? 'Lavoro completato e verificato.' : null,
        ]);
        $interventionIds[] = (int) $pdo->lastInsertId();
    }

    // --- Status history (creation baseline) ----------------------------------
    $histStmt = $pdo->prepare(
        'INSERT INTO intervention_status_history (intervention_id, from_status, to_status, changed_by)
         VALUES (:intervention_id, NULL, :to_status, :changed_by)'
    );
    foreach ($interventionIds as $idx => $intId) {
        $histStmt->execute([
            ':intervention_id' => $intId,
            ':to_status'       => $interventions[$idx][5],
            ':changed_by'      => $adminId,
        ]);
    }

    // --- Sample planned materials (planned only; reservation logic = Phase 4) -
    $matStmt = $pdo->prepare(
        'INSERT INTO intervention_materials (intervention_id, item_id, qty_planned, qty_used, is_reserved)
         VALUES (:intervention_id, :item_id, :qty_planned, :qty_used, 0)'
    );
    // First in_progress intervention uses cement + sand (planned, not yet reserved).
    $matStmt->execute([':intervention_id' => $interventionIds[0], ':item_id' => $itemIds[0], ':qty_planned' => '20.000', ':qty_used' => null]);
    $matStmt->execute([':intervention_id' => $interventionIds[0], ':item_id' => $itemIds[1], ':qty_planned' => '300.000', ':qty_used' => null]);
    // Completed intervention records what was planned/used (no movements pre-baked).
    $matStmt->execute([':intervention_id' => $interventionIds[3], ':item_id' => $itemIds[5], ':qty_planned' => '50.000', ':qty_used' => '48.000']);

    // --- Compliance documents (Scadenzario Sicurezza) ------------------------
    // Dates are relative to "today" so the ≤30-day dashboard widget always has
    // one expiring and one already-expired item to show.
    $todayDt = new DateTimeImmutable($today);
    $compStmt = $pdo->prepare(
        'INSERT INTO compliance_documents
            (subject_type, subject_id, doc_type, reference, issue_date, expiry_date, credits, notes, created_by)
         VALUES (:st, :sid, :dt, :ref, :issue, :expiry, :credits, :notes, :by)'
    );
    $compliance = [
        // [subject_type, subject_id, doc_type, reference, issue(+/-days), expiry(+/-days), credits]
        ['company', null, 'DURC', 'DURC-2026-Q3', -80, 20, null],                       // expiring soon
        ['company', null, 'patente_crediti', 'PAT-IMPRESA', -300, 400, 90],             // credits license
        ['subcontractor', $subcontractorIds[0], 'DURC', 'DURC-SUB-001', -120, -3, null],// EXPIRED
        ['worker', $worker1Id, 'visita_medica', 'VM-LUCA-2026', -30, 335, null],        // valid
        ['worker', $worker2Id, 'formazione', 'CORSO-PONTEGGI', -400, 25, null],         // expiring soon
    ];
    foreach ($compliance as $c) {
        $compStmt->execute([
            ':st'     => $c[0],
            ':sid'    => $c[1],
            ':dt'     => $c[2],
            ':ref'    => $c[3],
            ':issue'  => $todayDt->modify(($c[4] >= 0 ? '+' : '') . $c[4] . ' days')->format('Y-m-d'),
            ':expiry' => $todayDt->modify(($c[5] >= 0 ? '+' : '') . $c[5] . ' days')->format('Y-m-d'),
            ':credits' => $c[6],
            ':notes'  => null,
            ':by'     => $adminId,
        ]);
    }

    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "ERRORE durante il seed: {$e->getMessage()}\n");
    exit(1);
}

// --- Summary ----------------------------------------------------------------
$counts = [];
foreach ($dataTables as $table) {
    $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
}
echo "Seed completato:\n";
foreach ($counts as $table => $n) {
    printf("  %-28s %d\n", $table, $n);
}
echo "\nCredenziali (password per tutti: \"password\"):\n";
echo "  admin@gestionale.local   (amministratore)\n";
echo "  worker1@gestionale.local (operaio)\n";
echo "  worker2@gestionale.local (operaio)\n";
echo "  client1@gestionale.local (cliente - Edilizia Rossi)\n";
echo "  client2@gestionale.local (cliente - Costruzioni Bianchi)\n";
echo "  sub1@gestionale.local    (subappaltatore - Impianti Marche)\n";
