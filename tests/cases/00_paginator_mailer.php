<?php
/**
 * Unit tests (in-process, no HTTP) for the platform additions:
 *   - Paginator clamping / offset math
 *   - ReportFilename prefix (invoice/quote download names)
 *   - Mailer message construction + disabled-by-default gate
 */
declare(strict_types=1);

use App\Services\Report\ReportFilename;
use App\Support\Mailer;
use App\Support\Paginator;

T::section('Unit: Paginator');
$empty = new Paginator(0, 1, 25);
T::equals(1, $empty->pages, 'empty set is 1 page');
T::equals(0, $empty->offset, 'empty set offset 0');
T::equals(0, $empty->from(), 'empty set from() is 0');
T::ok(!$empty->hasPages(), 'empty set has no extra pages');

$p1 = new Paginator(50, 1, 20);
T::equals(3, $p1->pages, '50/20 = 3 pages');
T::equals(0, $p1->offset, 'page 1 offset 0');
T::equals(1, $p1->from(), 'page 1 from() 1');
T::equals(20, $p1->to(), 'page 1 to() 20');
T::ok($p1->hasPages(), 'multi-page reports hasPages');

$p2 = new Paginator(50, 2, 20);
T::equals(20, $p2->offset, 'page 2 offset 20');
T::equals(21, $p2->from(), 'page 2 from() 21');
T::equals(40, $p2->to(), 'page 2 to() 40');

$over = new Paginator(50, 99, 20);
T::equals(3, $over->page, 'page above max clamps to last');
T::equals(50, $over->to(), 'last page to() = total');

$under = new Paginator(50, -5, 20);
T::equals(1, $under->page, 'page below 1 clamps to 1');

$capped = new Paginator(10, 1, 9999);
T::equals(200, $capped->perPage, 'perPage capped at 200');

T::section('Unit: ReportFilename prefix');
T::equals('report-villa-rossi.pdf', ReportFilename::make('Villa Rossi', 'pdf'), 'default prefix is "report"');
T::equals('fattura-2026-001.pdf', ReportFilename::make('2026/001', 'pdf', 'fattura'), 'invoice prefix honored');
T::equals('preventivo-2026-002.pdf', ReportFilename::make('2026/002', 'pdf', 'preventivo'), 'quote prefix honored');
T::equals('report-villa-rossi-2.xlsx', ReportFilename::make('Villa Rossi 2!', 'xlsx'), 'non-alnum slugified');

T::section('Unit: Mailer (disabled by default)');
T::ok(!Mailer::isEnabled(), 'mail disabled by default in tests');
T::ok(Mailer::send('a@b.com', 'x', '<b>y</b>') === false, 'send() no-ops (returns false) when disabled');
T::equals(
    ['a@b.com', 'c@d.com'],
    Mailer::normalizeRecipients(['a@b.com', 'not-an-email', ' c@d.com ', 'a@b.com']),
    'recipients trimmed, validated, de-duplicated'
);
T::equals('Ciao', Mailer::encodeHeader('Ciao'), 'ASCII header left as-is');
T::ok(str_starts_with(Mailer::encodeHeader('Società'), '=?UTF-8?B?'), 'accented header RFC 2047 encoded');

$mime = Mailer::buildMimeMessage(['a@b.com'], 'Oggetto', '<b>corpo</b>', 'Wed, 10 Jul 2026 00:00:00 +0000');
T::ok(str_contains($mime, 'To: a@b.com'), 'MIME has To header');
T::ok(str_contains($mime, 'Content-Type: text/html; charset=UTF-8'), 'MIME is HTML');
T::ok(str_contains($mime, base64_encode('<b>corpo</b>')), 'MIME body is base64 of the HTML');
