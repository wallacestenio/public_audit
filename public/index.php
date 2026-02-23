<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

/* ================= Autoloader PSR-4 (sem Composer) ================= */
spl_autoload_register(function (string $class): void {
    $prefixes = [
        'App\\' => __DIR__ . '/../app/',
        'Src\\' => __DIR__ . '/../src/',
    ];
    foreach ($prefixes as $prefix => $baseDir) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) continue;
        $relative = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) { require $file; return; }
    }
});

/* ================= Config ================= */
$configPath = __DIR__ . '/../config/config.php';
$config = is_file($configPath) ? require $configPath : [
    'sqlite_path' => __DIR__ . '/../database/tickets.db',
    'schema_sql'  => __DIR__ . '/../database/tickets.sql',
];

/* ================= DB (via Src\Database) ================= */
use Src\Database;

try {
    $db  = new Database($config['sqlite_path'], $config['schema_sql']);
    $pdo = $db->pdo();
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Falha ao inicializar o banco de dados.\n";
    echo "Caminho SQLite: {$config['sqlite_path']}\n";
    echo "Erro: " . $e->getMessage() . "\n";
    exit;
}

/* ================= DI manual ================= */
use App\Core\Router;

use App\Models\{Presets, AuditEntry, SchemaInspector};
use App\Repositories\{CatalogRepository, AuditEntryRepository};
use App\Services\{PayloadMapper, CreateAuditEntryService};
use App\Controllers\{CatalogController, AuditEntriesController};

// (Opcional) Logger
$logger = class_exists(\App\Support\Logger::class) ? new \App\Support\Logger() : null;

// Auxiliares p/ Mapper/Schema
$schema = new SchemaInspector($pdo);
$mapper = new PayloadMapper($schema, $pdo);

// Presets/Catálogo
$presets     = new Presets($pdo);
$catalogRepo = new CatalogRepository($pdo);
$catalogCtrl = new CatalogController($catalogRepo);

// Auditoria
$auditModel = new AuditEntry($pdo);
$auditRepo  = new AuditEntryRepository($auditModel);
$createSvc  = new CreateAuditEntryService($auditRepo);
$auditCtrl  = new AuditEntriesController($createSvc, $auditRepo, $logger);

/* ================= Router e Rotas ================= */
$router = new Router();

// Feito com Closures -> compatível com o Router atual
$router->get('/',               fn() => $auditCtrl->form());
$router->post('/audit-entries', fn() => $auditCtrl->store());

$router->get('/api/catalog',    fn() => $catalogCtrl->autocomplete());

$router->get('/export/csv',     fn() => $auditCtrl->exportCsv());

// (Opcional) export ponte (se sua camada suportar)
if (method_exists($auditCtrl, 'exportBridgeCsv')) {
    $router->get('/export/bridge', fn() => $auditCtrl->exportBridgeCsv());
}

// Diagnóstico rápido
$router->get('/debug/health', function () use ($config, $pdo) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "sqlite_path: {$config['sqlite_path']}\n";

    try {
        $rows = $pdo->query("PRAGMA database_list;")->fetchAll(PDO::FETCH_ASSOC);
        echo "PRAGMA database_list:\n";
        foreach ($rows as $r) {
            echo "- {$r['name']}: {$r['file']}\n";
        }
    } catch (\Throwable $e) {
        echo "Erro PRAGMA database_list: " . $e->getMessage() . "\n";
    }

    echo "\nColumns in audit_entries:\n";
    try {
        $cols = $pdo->query("PRAGMA table_info(audit_entries)")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $c) {
            $name = $c['name'] ?? '?';
            $type = $c['type'] ?? '?';
            echo "- {$name} ({$type})\n";
        }
    } catch (\Throwable $e) {
        echo "Erro PRAGMA table_info(audit_entries): " . $e->getMessage() . "\n";
    }
});

/* ================= Dispatch ================= */
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = $_SERVER['REQUEST_URI']   ?? '/';
$router->dispatch($method, $uri);