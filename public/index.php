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
use App\Controllers\Admin\InterventionController;
use App\Controllers\Admin\PhotoController as AdminPhotoController;
use App\Controllers\Admin\ProjectController;
use App\Controllers\Admin\ReportController as AdminReportController;
use App\Controllers\Admin\UserController;
use App\Controllers\Admin\WarehouseController;
use App\Controllers\AuthController;
use App\Controllers\Client\PhotoController as ClientPhotoController;
use App\Controllers\Client\ProjectController as ClientProjectController;
use App\Controllers\Client\ReportController as ClientReportController;
use App\Controllers\DashboardController;
use App\Controllers\Worker\PhotoController;
use App\Controllers\Worker\TaskController;
use App\Http\Router;
use App\Support\Config;
use App\Support\Csrf;
use App\Support\Lang;
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

View::share([
    'base' => $base,
    'user' => Auth::user(),
]);

$router = new Router();
$router->get('/',        [DashboardController::class, 'home']);
$router->get('/login',   [AuthController::class, 'show']);
$router->post('/login',  [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);
$router->get('/password',  [AuthController::class, 'showPassword']);
$router->post('/password', [AuthController::class, 'changePassword']);
$router->get('/admin',   [DashboardController::class, 'admin']);
$router->get('/health',  [DashboardController::class, 'health']);

$router->get('/admin/clients',              [ClientController::class, 'index']);
$router->post('/admin/clients',             [ClientController::class, 'store']);
$router->post('/admin/clients/{id}',        [ClientController::class, 'update']);
$router->post('/admin/clients/{id}/delete', [ClientController::class, 'destroy']);

$router->get('/admin/projects',              [ProjectController::class, 'index']);
$router->post('/admin/projects',             [ProjectController::class, 'store']);
$router->post('/admin/projects/{id}',        [ProjectController::class, 'update']);
$router->post('/admin/projects/{id}/delete', [ProjectController::class, 'destroy']);

$router->get('/admin/warehouse',                 [WarehouseController::class, 'index']);
$router->post('/admin/warehouse',                [WarehouseController::class, 'store']);
$router->get('/admin/warehouse/{id}',            [WarehouseController::class, 'show']);
$router->post('/admin/warehouse/{id}',           [WarehouseController::class, 'update']);
$router->post('/admin/warehouse/{id}/toggle',    [WarehouseController::class, 'toggleActive']);
$router->post('/admin/warehouse/{id}/movement',  [WarehouseController::class, 'addMovement']);
$router->post('/admin/warehouse/{id}/reconcile', [WarehouseController::class, 'reconcile']);

$router->get('/admin/interventions',               [InterventionController::class, 'index']);
$router->post('/admin/interventions',               [InterventionController::class, 'store']);
$router->get('/admin/interventions/{id}',           [InterventionController::class, 'show']);
$router->post('/admin/interventions/{id}',          [InterventionController::class, 'update']);
$router->post('/admin/interventions/{id}/status',   [InterventionController::class, 'status']);
$router->get('/admin/interventions/{id}/signature', [InterventionController::class, 'signature']);

$router->get('/admin/users',              [UserController::class, 'index']);
$router->post('/admin/users',             [UserController::class, 'store']);
$router->post('/admin/users/{id}',        [UserController::class, 'update']);
$router->post('/admin/users/{id}/toggle', [UserController::class, 'toggleActive']);

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

$router->get('/client',                          [ClientProjectController::class, 'index']);
$router->get('/client/projects/{id}',             [ClientProjectController::class, 'show']);
$router->get('/client/photos/{id}',               [ClientPhotoController::class, 'show']);
$router->get('/client/photos/{id}/thumb',         [ClientPhotoController::class, 'thumb']);
$router->get('/client/projects/{id}/report/pdf',   [ClientReportController::class, 'pdf']);
$router->get('/client/projects/{id}/report/excel', [ClientReportController::class, 'excel']);

// Catch-all (§8 polish): an uncaught exception must never leak a raw PHP error
// page or stack trace to the user. Always logged; only shown verbatim in debug.
try {
    $router->dispatch($request);
} catch (\Throwable $e) {
    error_log('[' . $request->method . ' ' . $request->path . '] ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString());

    if (Config::get('app.debug', false)) {
        throw $e;
    }

    if ($request->wantsJson()) {
        Response::fail('Si è verificato un errore imprevisto.', 500);
    } else {
        Response::html(View::render('errors/500', ['title' => 'Errore'], 'layout'), 500);
    }
}
