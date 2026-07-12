<?php
/** Unit tests: quantity validation, CSRF token logic. In-process, no HTTP. */
declare(strict_types=1);

use App\Services\Report\MpdfFactory;
use App\Support\Config;
use App\Support\Csrf;
use App\Support\Logger;
use App\Support\Validate;
use Mpdf\Output\Destination;

T::section('Unit: Validate::isQty');
T::ok(Validate::isQty('10'), 'accepts integer');
T::ok(Validate::isQty('0.5'), 'accepts decimal');
T::ok(Validate::isQty('-3.25'), 'accepts negative (adjustments)');
T::ok(!Validate::isQty(''), 'rejects empty string');
T::ok(!Validate::isQty('abc'), 'rejects non-numeric');
T::ok(!Validate::isQty('10,5'), 'rejects comma decimal separator');
T::ok(Validate::isQty('999999999.999'), 'accepts DECIMAL(12,3) max');
T::ok(!Validate::isQty('1000000000'), 'rejects DECIMAL(12,3) overflow');
T::ok(!Validate::isQty('-999999999999'), 'rejects negative overflow');

T::section('Unit: CSRF');
$_SESSION = [];
$token1 = Csrf::token();
$token2 = Csrf::token();
T::ok(strlen($token1) === 64, 'token is 64 hex chars');
T::equals($token1, $token2, 'token is stable within a session');
T::ok(Csrf::check($token1), 'valid token passes');
T::ok(!Csrf::check('deadbeef'), 'wrong token fails');
T::ok(!Csrf::check(null), 'null token fails');
T::ok(!Csrf::check(''), 'empty token fails');
$_SESSION = [];
T::ok(Csrf::token() !== $token1, 'new session gets a new token');
$_SESSION = [];

// Regression: PDF builders must render to a writable tempDir. mPDF's own
// default (vendor/mpdf/mpdf/tmp) is root-owned/read-only in the production
// container (PHP-FPM runs as www-data), so an unconfigured instance throws
// "Temporary files directory ... is not writable" and every PDF endpoint 500s.
T::section('Unit: MpdfFactory tempDir');
$tempDir = (string) Config::get('storage.pdf_temp_path');
T::ok($tempDir !== '' && strpos($tempDir, 'vendor') === false, 'temp dir is outside vendor/');
$mpdf = MpdfFactory::create(['format' => 'A4']);
T::ok(is_dir($tempDir), 'factory creates the temp dir');
T::ok(is_writable($tempDir), 'temp dir is writable');
$mpdf->WriteHTML('<h1>Test</h1><p>Regression guard for mPDF temp dir.</p>');
$pdf = $mpdf->Output('', Destination::STRING_RETURN);
T::ok(strncmp($pdf, '%PDF', 4) === 0, 'renders a valid PDF to the configured temp dir');

// Uncaught 500s must be logged as one structured, correlatable JSON line so a
// production error is never silent again (regression guard for the observability
// added after the 2026-07-11 PDF incident).
T::section('Unit: Logger');
$rid = Logger::requestId();
T::ok(strlen($rid) === 12 && ctype_xdigit($rid), 'request id is 12 hex chars');
T::equals($rid, Logger::requestId(), 'request id is stable within a request');

$logFile = tempnam(sys_get_temp_dir(), 'gmlog');
$prevLog = ini_get('error_log');
ini_set('error_log', $logFile);
Logger::exception(new RuntimeException('kaboom'), ['method' => 'GET', 'path' => '/x']);
ini_set('error_log', $prevLog === false ? '' : $prevLog);

$content = (string) file_get_contents($logFile);
@unlink($logFile);
// error_log to a file prepends "[date] "; stderr (production) does not — locate
// the greppable marker rather than assume it starts the line.
$pos = strpos($content, 'gm ');
T::ok($pos !== false, 'log line carries the greppable "gm " marker');
$decoded = json_decode(trim(substr($content, (int) $pos + 3)), true);
T::ok(is_array($decoded), 'log line is valid JSON');
T::equals('RuntimeException', $decoded['type'] ?? null, 'logs the exception type');
T::equals($rid, $decoded['request_id'] ?? null, 'log line carries the request id shown to the user');
T::equals('/x', $decoded['path'] ?? null, 'log line carries request context');
