<?php
/**
 * Front controller / router entry.
 */
declare(strict_types=1);

// Serve existing static files as-is when running under `php -S`.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Controllers\Admin\ClientController;
use App\Controllers\Admin\ExpenseController;
use App\Controllers\Admin\InterventionController;
use App\Controllers\Admin\InvoiceController;
use App\Controllers\Admin\NotificationController;
use App\Controllers\Admin\QuoteController;
use App\Controllers\Admin\PhotoController as AdminPhotoController;
use App\Controllers\Admin\ProjectController;
use App\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Controllers\Admin\ComplianceController;
use App\Controllers\Admin\DailyLogController;
use App\Controllers\Admin\ExportController;
use App\Controllers\Admin\FinancialsController;
use App\Controllers\Admin\ReportController as AdminReportController;
use App\Controllers\Admin\SalController;
use App\Controllers\Admin\SearchController;
use App\Controllers\Admin\StatisticsController;
use App\Controllers\Admin\SubcontractorController;
use App\Controllers\Admin\UserController;
use App\Controllers\Admin\WarehouseController;
use App\Controllers\AuthController;
use App\Controllers\Client\PhotoController as ClientPhotoController;
use App\Controllers\Client\ProjectController as ClientProjectController;
use App\Controllers\Client\QuoteController as ClientQuoteController;
use App\Controllers\Client\ReportController as ClientReportController;
use App\Controllers\AttendanceController;
use App\Controllers\DashboardController;
use App\Controllers\Sub\PhotoController as SubPhotoController;
use App\Controllers\Sub\ProjectController as SubProjectController;
use App\Controllers\Worker\PhotoController;
use App\Controllers\Worker\TaskController;
use App\Http\Router;
use App\Support\Config;
use App\Support\Csrf;
use App\Support\Lang;
use App\Support\Logger;
use App\Support\Request;
use App\Support\Response;
use App\Support\Session;
use App\Support\Url;
use App\Support\View;
use App\Support\Auth;

Session::start();
Lang::load();

// Security headers on every response. HSTS is added at the web-server layer.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

// Base path: empty under the dev server, the script's dir under Apache.
$base = PHP_SAPI === 'cli-server'
    ? ''
    : rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? ''))), '/');
Url::setBase($base);

$request = Request::fromGlobals($base);

// Idle-session timeout: expire authenticated sessions after inactivity.
if (Auth::check() && Session::idleExpired((int) Config::get('session.idle_timeout', 28800))) {
    Auth::logout();
    if ($request->wantsJson()) {
        Response::fail(Lang::get('auth.session_expired'), 401);
        exit;
    }
    Response::redirect(Url::to('/login'));
    exit;
}
Session::touch();

// CSRF: every POST must carry the session token (X-CSRF-Token header or _token field).
if ($request->isPost() && !Csrf::check(Csrf::fromRequest($request))) {
    if ($request->wantsJson()) {
        Response::fail(Lang::get('auth.csrf_invalid'), 403);
    } else {
        Response::html(View::render('errors/403', ['title' => 'Accesso negato'], 'layout'), 403);
    }
    exit;
}

// Unread notification count for the admin topbar bell (one indexed COUNT for
// admins only; every other role skips the query).
$notifUnread = 0;
if (Auth::role() === 'admin') {
    try {
        $notifUnread = (new \App\Models\NotificationModel())->unreadCount();
    } catch (\Throwable $e) {
        $notifUnread = 0; // e.g. before the notifications migration has run
    }
}

View::share([
    'base'        => $base,
    'user'        => Auth::user(),
    'notifUnread' => $notifUnread,
]);

$router = new Router();
$router->get('/',        [DashboardController::class, 'home']);
$router->get('/login',   [AuthController::class, 'show']);
$router->post('/login',  [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/password',  [AuthController::class, 'showPassword']);
$router->post('/password', [AuthController::class, 'changePassword']);
$router->get('/admin',   [DashboardController::class, 'admin']);
$router->get('/admin/statistics', [StatisticsController::class, 'index']);
$router->get('/admin/financials', [FinancialsController::class, 'index']);
$router->get('/admin/search',     [SearchController::class, 'index']);
$router->get('/health',  [DashboardController::class, 'health']);
$router->get('/shortcuts', [DashboardController::class, 'shortcuts']);
$router->post('/shortcuts', [DashboardController::class, 'saveShortcuts']);

$router->get('/admin/clients',              [ClientController::class, 'index']);
$router->get('/admin/clients/create',       [ClientController::class, 'create']);
$router->get('/admin/clients/{id}/edit',    [ClientController::class, 'edit']);
$router->post('/admin/clients',             [ClientController::class, 'store']);
$router->post('/admin/clients/{id}',        [ClientController::class, 'update']);
$router->post('/admin/clients/{id}/delete', [ClientController::class, 'destroy']);

$router->get('/admin/projects',              [ProjectController::class, 'index']);
$router->get('/admin/projects/create',       [ProjectController::class, 'create']);
$router->get('/admin/projects/{id}/edit',    [ProjectController::class, 'edit']);
$router->get('/admin/projects/{id}',         [ProjectController::class, 'show']);
$router->post('/admin/projects',             [ProjectController::class, 'store']);
$router->post('/admin/projects/{id}',        [ProjectController::class, 'update']);
$router->post('/admin/projects/{id}/delete', [ProjectController::class, 'destroy']);
// Project detail page sub-resources (documents, invoices, materials, attendance, workers)
$router->post('/admin/projects/{id}/documents',                    [ProjectController::class, 'uploadDocument']);
$router->get('/admin/projects/{id}/documents/{docId}',             [ProjectController::class, 'downloadDocument']);
$router->post('/admin/projects/{id}/documents/{docId}/delete',     [ProjectController::class, 'deleteDocument']);
$router->post('/admin/projects/{id}/invoices',                     [ProjectController::class, 'storeInvoice']);
$router->post('/admin/projects/{id}/invoices/{invoiceId}/delete',  [ProjectController::class, 'deleteInvoice']);
$router->post('/admin/projects/{id}/notes',                        [ProjectController::class, 'storeNote']);
$router->post('/admin/projects/{id}/notes/{noteId}/toggle',        [ProjectController::class, 'toggleNote']);
$router->post('/admin/projects/{id}/notes/{noteId}/delete',        [ProjectController::class, 'deleteNote']);
$router->post('/admin/projects/{id}/materials',                    [ProjectController::class, 'storeMaterial']);
$router->post('/admin/projects/{id}/materials/{materialId}/delete',[ProjectController::class, 'deleteMaterial']);
$router->post('/admin/projects/{id}/attendance',                   [ProjectController::class, 'toggleAttendance']);
$router->post('/admin/projects/{id}/workers',                      [ProjectController::class, 'assignWorker']);
$router->post('/admin/projects/{id}/workers/{workerId}/remove',    [ProjectController::class, 'unassignWorker']);

$router->get('/admin/warehouse',                 [WarehouseController::class, 'index']);
$router->get('/admin/warehouse/create',          [WarehouseController::class, 'create']);
$router->get('/admin/warehouse/{id}/edit',       [WarehouseController::class, 'edit']);
$router->post('/admin/warehouse',                [WarehouseController::class, 'store']);
$router->get('/admin/warehouse/{id}',            [WarehouseController::class, 'show']);
$router->post('/admin/warehouse/{id}',           [WarehouseController::class, 'update']);
$router->post('/admin/warehouse/{id}/toggle',    [WarehouseController::class, 'toggleActive']);
$router->post('/admin/warehouse/{id}/movement',  [WarehouseController::class, 'addMovement']);
$router->post('/admin/warehouse/{id}/reconcile', [WarehouseController::class, 'reconcile']);
$router->post('/admin/warehouse/{id}/transfer',  [WarehouseController::class, 'transfer']);

$router->get('/admin/interventions',               [InterventionController::class, 'index']);
$router->get('/admin/interventions/create',         [InterventionController::class, 'create']);
$router->get('/admin/interventions/{id}/edit',      [InterventionController::class, 'edit']);
$router->post('/admin/interventions',               [InterventionController::class, 'store']);
$router->get('/admin/interventions/{id}',           [InterventionController::class, 'show']);
$router->post('/admin/interventions/{id}',          [InterventionController::class, 'update']);
$router->post('/admin/interventions/{id}/status',   [InterventionController::class, 'status']);
$router->get('/admin/interventions/{id}/signature', [InterventionController::class, 'signature']);

// --- Preventivi (Quotes) ---
$router->get('/admin/quotes',               [QuoteController::class, 'index']);
$router->get('/admin/quotes/create',        [QuoteController::class, 'create']);
$router->get('/admin/quotes/{id}/edit',     [QuoteController::class, 'edit']);
$router->get('/admin/quotes/{id}/pdf',      [QuoteController::class, 'pdf']);
$router->post('/admin/quotes',              [QuoteController::class, 'store']);
$router->post('/admin/quotes/{id}',         [QuoteController::class, 'update']);
$router->post('/admin/quotes/{id}/delete',  [QuoteController::class, 'destroy']);
$router->post('/admin/quotes/{id}/invoice', [QuoteController::class, 'toInvoice']);

// --- Fatture (Invoices) ---
$router->get('/admin/invoices',               [InvoiceController::class, 'index']);
$router->get('/admin/invoices/create',        [InvoiceController::class, 'create']);
$router->get('/admin/invoices/{id}/edit',     [InvoiceController::class, 'edit']);
$router->get('/admin/invoices/{id}/print',    [InvoiceController::class, 'print']);
$router->post('/admin/invoices',              [InvoiceController::class, 'store']);
$router->post('/admin/invoices/{id}',         [InvoiceController::class, 'update']);
$router->post('/admin/invoices/{id}/delete',  [InvoiceController::class, 'destroy']);

// --- Spese (Expenses) ---
$router->get('/admin/expenses',               [ExpenseController::class, 'index']);
$router->get('/admin/expenses/create',        [ExpenseController::class, 'create']);
$router->get('/admin/expenses/{id}/edit',     [ExpenseController::class, 'edit']);
$router->post('/admin/expenses',              [ExpenseController::class, 'store']);
$router->post('/admin/expenses/{id}',         [ExpenseController::class, 'update']);
$router->post('/admin/expenses/{id}/delete',  [ExpenseController::class, 'destroy']);

$router->get('/admin/users',              [UserController::class, 'index']);
$router->get('/admin/users/create',       [UserController::class, 'create']);
$router->get('/admin/users/{id}/edit',    [UserController::class, 'edit']);
$router->post('/admin/users',             [UserController::class, 'store']);
$router->post('/admin/users/{id}',        [UserController::class, 'update']);
$router->post('/admin/users/{id}/toggle', [UserController::class, 'toggleActive']);

$router->get('/admin/notifications',                 [NotificationController::class, 'index']);
$router->post('/admin/notifications/read-all',       [NotificationController::class, 'readAll']);
$router->post('/admin/notifications/{id}/read',      [NotificationController::class, 'read']);

$router->get('/admin/attendance',                    [AdminAttendanceController::class, 'index']);

$router->get('/admin/daily-logs',                    [DailyLogController::class, 'index']);
$router->get('/admin/daily-logs/create',             [DailyLogController::class, 'create']);
$router->post('/admin/daily-logs',                   [DailyLogController::class, 'store']);
$router->get('/admin/daily-logs/{id}',               [DailyLogController::class, 'show']);
$router->post('/admin/daily-logs/{id}',              [DailyLogController::class, 'update']);
$router->post('/admin/daily-logs/{id}/close',        [DailyLogController::class, 'close']);
$router->post('/admin/daily-logs/{id}/equipment',    [DailyLogController::class, 'equipment']);
$router->post('/admin/equipment',                    [DailyLogController::class, 'storeEquipment']);

$router->get('/admin/sal',                           [SalController::class, 'index']);
$router->get('/admin/sal/create',                    [SalController::class, 'create']);
$router->post('/admin/sal',                          [SalController::class, 'store']);
$router->get('/admin/sal/{id}',                      [SalController::class, 'show']);
$router->post('/admin/sal/{id}',                     [SalController::class, 'update']);
$router->post('/admin/sal/{id}/lines',               [SalController::class, 'addLine']);
$router->post('/admin/sal/{id}/lines/{lineId}/delete', [SalController::class, 'deleteLine']);
$router->post('/admin/sal/{id}/issue',               [SalController::class, 'issue']);
$router->post('/admin/sal/{id}/sign',                [SalController::class, 'sign']);
$router->get('/admin/sal/{id}/pdf',                  [SalController::class, 'pdf']);

$router->get('/admin/compliance',                    [ComplianceController::class, 'index']);
$router->get('/admin/compliance/create',             [ComplianceController::class, 'create']);
$router->get('/admin/compliance/{id}/edit',          [ComplianceController::class, 'edit']);
$router->post('/admin/compliance',                   [ComplianceController::class, 'store']);
$router->post('/admin/compliance/{id}',              [ComplianceController::class, 'update']);
$router->post('/admin/compliance/{id}/delete',       [ComplianceController::class, 'destroy']);

$router->get('/admin/exports',                       [ExportController::class, 'index']);
$router->get('/admin/exports/accountant',            [ExportController::class, 'accountant']);

$router->get('/admin/subcontractors',                [SubcontractorController::class, 'index']);
$router->get('/admin/subcontractors/create',         [SubcontractorController::class, 'create']);
$router->get('/admin/subcontractors/{id}/edit',      [SubcontractorController::class, 'edit']);
$router->post('/admin/subcontractors',               [SubcontractorController::class, 'store']);
$router->post('/admin/subcontractors/{id}',          [SubcontractorController::class, 'update']);
$router->post('/admin/subcontractors/{id}/toggle',   [SubcontractorController::class, 'toggleActive']);
$router->post('/admin/subcontractors/{id}/projects', [SubcontractorController::class, 'assignProjects']);

$router->get('/admin/photos/{id}',        [AdminPhotoController::class, 'show']);
$router->get('/admin/photos/{id}/thumb',  [AdminPhotoController::class, 'thumb']);

$router->get('/admin/projects/{id}/report/pdf',    [AdminReportController::class, 'pdf']);
$router->get('/admin/projects/{id}/report/excel',  [AdminReportController::class, 'excel']);

$router->get('/worker',                                  [TaskController::class, 'today']);
$router->get('/worker/interventions/{id}',                [TaskController::class, 'show']);
$router->post('/worker/interventions/{id}/status',         [TaskController::class, 'status']);
$router->post('/worker/interventions/{id}/complete',       [TaskController::class, 'complete']);
$router->post('/worker/interventions/{id}/signature',      [TaskController::class, 'saveSignature']);
$router->get('/worker/interventions/{id}/signature',       [TaskController::class, 'signature']);
$router->post('/worker/interventions/{id}/photos',         [PhotoController::class, 'upload']);
$router->get('/worker/photos/{id}',                        [PhotoController::class, 'show']);
$router->get('/worker/photos/{id}/thumb',                  [PhotoController::class, 'thumb']);

$router->get('/attendance',       [AttendanceController::class, 'page']);
$router->post('/attendance/in',   [AttendanceController::class, 'clockIn']);
$router->post('/attendance/out',  [AttendanceController::class, 'clockOut']);

$router->get('/sub',                        [SubProjectController::class, 'index']);
$router->get('/sub/projects/{id}',           [SubProjectController::class, 'show']);
$router->get('/sub/photos/{id}',             [SubPhotoController::class, 'show']);
$router->get('/sub/photos/{id}/thumb',       [SubPhotoController::class, 'thumb']);

$router->get('/client',                          [ClientProjectController::class, 'index']);
$router->get('/client/projects/{id}',             [ClientProjectController::class, 'show']);
$router->get('/client/quotes',                     [ClientQuoteController::class, 'index']);
$router->get('/client/quotes/{id}',                [ClientQuoteController::class, 'show']);
$router->post('/client/quotes/{id}/accept',        [ClientQuoteController::class, 'accept']);
$router->post('/client/quotes/{id}/reject',        [ClientQuoteController::class, 'reject']);
$router->get('/client/photos/{id}',               [ClientPhotoController::class, 'show']);
$router->get('/client/photos/{id}/thumb',         [ClientPhotoController::class, 'thumb']);
$router->get('/client/projects/{id}/report/pdf',   [ClientReportController::class, 'pdf']);
$router->get('/client/projects/{id}/report/excel', [ClientReportController::class, 'excel']);

// Catch-all (§8 polish): an uncaught exception must never leak a raw PHP error
// page or stack trace to the user. Always logged; only shown verbatim in debug.
try {
    $router->dispatch($request);
} catch (\Throwable $e) {
    Logger::exception($e, [
        'method'  => $request->method,
        'path'    => $request->path,
        'user_id' => Auth::id(),
    ]);

    if (Config::get('app.debug', false)) {
        throw $e;
    }

    $ref = Logger::requestId();
    if ($request->wantsJson()) {
        Response::fail(Lang::get('errors.unexpected') . ' (' . Lang::get('errors.reference') . ': ' . $ref . ')', 500);
    } else {
        Response::html(View::render('errors/500', ['title' => 'Errore', 'ref' => $ref], 'layout'), 500);
    }
}
