<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

/* Sessão */
session_start();

/* Autoloader PSR-4 (sem Composer) */
spl_autoload_register(function (string $class): void {
    $prefixes = ['App\\' => __DIR__ . '/../app/', 'Src\\' => __DIR__ . '/../src/'];
    foreach ($prefixes as $prefix => $baseDir) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) continue;
        $relative = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) { require $file; return; }
    }
});

function base_path(): string {
    $s = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $d = rtrim(str_replace('\\', '/', dirname($s)), '/');
    return ($d === '/' || $d === '.') ? '' : $d;
}

/* Config / DB */
$configPath = __DIR__ . '/../config/config.php';
$config = is_file($configPath) ? require $configPath : [
    'sqlite_path' => __DIR__ . '/../database/tickets.db',
    'schema_sql'  => __DIR__ . '/../database/tickets.sql',
];

use Src\Database;

try {
    $db  = new Database($config['sqlite_path'], $config['schema_sql']);
    $pdo = $db->pdo(); // PRAGMA foreign_keys ON dentro do Database (garanta isso)
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Falha ao inicializar o banco de dados.\nErro: " . $e->getMessage() . "\n";
    exit;
}

/* DI */
use App\Core\Router;
use App\Models\{Presets, AuditEntry, SchemaInspector};
use App\Repositories\{CatalogRepository, AuditEntryRepository};
use App\Services\{PayloadMapper, CreateAuditEntryService};
use App\Controllers\{CatalogController, AuditEntriesController, LoginController};
use App\Support\Auth;

$router = new Router();

$schema = new SchemaInspector($pdo);
$mapper = new PayloadMapper($schema, $pdo);

$presets     = new Presets($pdo);
$catalogRepo = new CatalogRepository($pdo);
$catalogCtrl = new CatalogController($catalogRepo);

$auditModel = new AuditEntry($pdo);
$auditRepo  = new AuditEntryRepository($auditModel);
$createSvc  = new CreateAuditEntryService($auditRepo);
$logger     = class_exists(\App\Support\Logger::class) ? new \App\Support\Logger() : null;
$auditCtrl  = new AuditEntriesController($createSvc, $auditRepo, $logger);

$auth      = new Auth($pdo);
$loginCtrl = new LoginController($auth);

/* Guards simples */
$mustAuth  = function () use ($auth) { if (!$auth->check()) { header('Location: ' . base_path() . '/login'); exit; } };
$mustAdmin = function () use ($auth) { if (!$auth->isAdmin()) { header('Location: ' . base_path() . '/'); exit; } };

/* Rotas públicas (login) */
$router->get('/login',  fn() => $loginCtrl->show());
$router->post('/login', fn() => $loginCtrl->login());
$router->post('/logout', fn() => $loginCtrl->logout());

/* Rotas protegidas (ambos os perfis) */
$router->get('/',               function () use ($mustAuth, $auditCtrl) { $mustAuth(); $auditCtrl->form(); });
$router->post('/audit-entries', function () use ($mustAuth, $auditCtrl) { $mustAuth(); $auditCtrl->store(); });

/* API de catálogo (pode ser pública ou protegida; aqui deixei pública) */
$router->get('/api/catalog', fn() => $catalogCtrl->autocomplete());

/* Export (protegida) */
$router->get('/export/csv', function () use ($mustAuth, $auditCtrl) { $mustAuth(); $auditCtrl->exportCsv(); });

/* (Opcional) /admin — por enquanto só uma página placeholder */
$router->get('/admin', function () use ($mustAdmin) {
    $mustAdmin();
    $baseDir = __DIR__ . '/../app/Views';
    $layout  = $baseDir . '/layout.php';
    $view    = 'admin';
    $base    = base_path();
    $title   = 'Admin';
    require $layout;
});

/* Diagnóstico */
$router->get('/debug/health', function () use ($pdo) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "OK\n";
});

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');