<?php
/**
 * S.A.L. — Stato Avanzamento Lavori (v2 Phase 4c): per-project numbering, line
 * totals, and the draft → issued → signed state machine at the model layer.
 */
declare(strict_types=1);

use App\Models\SalDocumentModel;
use App\Models\SalLineModel;

/** @var PDO $pdo (from run.php) */

$docs  = new SalDocumentModel();
$lines = new SalLineModel();
$PROJECT = 4;

// ---------------------------------------------------------------------------
T::section('S.A.L.: per-project numbering');
$n1 = $docs->nextNumber($PROJECT);
$id1 = $docs->create(['project_id' => $PROJECT, 'number' => $n1, 'description' => 'Primo SAL', 'created_by' => 1]);
$n2 = $docs->nextNumber($PROJECT);
T::equals($n1 + 1, $n2, 'nextNumber increments per project');
$id2 = $docs->create(['project_id' => $PROJECT, 'number' => $n2, 'created_by' => 1]);
T::ok($id2 > 0, 'second document created');

// ---------------------------------------------------------------------------
T::section('S.A.L.: line totals');
$lines->create(['sal_id' => $id1, 'description' => 'Muratura', 'qty' => '10', 'unit' => 'm', 'unit_price' => '12.5000', 'amount' => '125.00']);
$lines->create(['sal_id' => $id1, 'description' => 'Intonaco', 'qty' => '4', 'unit' => 'm', 'unit_price' => '20.0000', 'amount' => '80.00']);
$total = $docs->recomputeAmount($id1);
T::equals('205.00', $total, 'recomputeAmount sums the line amounts');
T::equals(2, count($lines->forDocument($id1)), 'two lines on the document');

$row = $lines->forDocument($id1)[0];
$lines->delete((int) $row['id']);
T::equals('80.00', $docs->recomputeAmount($id1), 'total drops after a line is deleted');

// ---------------------------------------------------------------------------
T::section('S.A.L.: state machine draft -> issued -> signed');
T::equals('draft', (string) $docs->find($id1)['status'], 'new document is a draft');

// updateHeader works on a draft.
$docs->updateHeader($id1, ['period_from' => '2026-06-01', 'period_to' => '2026-06-30', 'description' => 'Giugno']);
T::equals('2026-06-01', (string) $docs->find($id1)['period_from'], 'draft header editable');

// markSigned must NOT transition a draft (guarded by WHERE status='issued').
$docs->markSigned($id1, 'x.png');
T::equals('draft', (string) $docs->find($id1)['status'], 'signing a draft is a no-op — still a draft');

// Issue it.
T::ok($docs->markIssued($id1, 'sal/4/sal-1.pdf'), 'markIssued transitions draft -> issued');
$issued = $docs->find($id1);
T::equals('issued', (string) $issued['status'], 'status is issued');
T::ok($issued['issued_at'] !== null, 'issued_at set');
T::equals('sal/4/sal-1.pdf', (string) $issued['pdf_path'], 'pdf_path stored');

// updateHeader is now a no-op (WHERE status = draft).
$docs->updateHeader($id1, ['description' => 'HACK AFTER ISSUE']);
T::equals('Giugno', (string) $docs->find($id1)['description'], 'issued header is frozen');

// markIssued again does nothing (not a draft).
$docs->markIssued($id1, 'y.pdf');
T::equals('sal/4/sal-1.pdf', (string) $docs->find($id1)['pdf_path'], 're-issuing does not overwrite the pdf_path');

// Sign it.
T::ok($docs->markSigned($id1, 'sal/4/sal-1-sign.png'), 'markSigned transitions issued -> signed');
$signed = $docs->find($id1);
T::equals('signed', (string) $signed['status'], 'status is signed');
T::ok($signed['signed_at'] !== null, 'signed_at set');
