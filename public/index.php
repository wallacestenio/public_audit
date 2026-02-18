<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

// Autoloader PSR-4 simples (sem Composer)
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

// Config
$configPath = __DIR__ . '/../config/config.php';
$config = is_file($configPath) ? require $configPath : [
    'sqlite_path' => __DIR__ . '/../database/tickets.db',
    'schema_sql'  => __DIR__ . '/../database/tickets.sql',
];

// DB
use Src\Database;
$db  = new Database($config['sqlite_path'], $config['schema_sql']);
$pdo = $db->pdo();

// DI manual
use App\Core\Router;
use App\Models\{Presets, AuditEntry, SchemaInspector};
use App\Repositories\{CatalogRepository, AuditEntryRepository};
use App\Services\{PayloadMapper, CreateAuditEntryService};
use App\Controllers\{CatalogController, AuditEntriesController};

$schema = new SchemaInspector($pdo);
$mapper = new PayloadMapper($schema, $pdo);

$presets     = new Presets($pdo);
$catalogRepo = new CatalogRepository($presets);
$catalogCtrl = new CatalogController($catalogRepo);

$auditModel = new AuditEntry($pdo);
$auditRepo  = new AuditEntryRepository($auditModel);
$createSvc  = new CreateAuditEntryService($auditRepo, $mapper, $schema);
$auditCtrl  = new AuditEntriesController($createSvc, $auditRepo);

$router = new Router();
$router->get('/', fn() => $auditCtrl->form());
$router->post('/audit-entries', fn() => $auditCtrl->store());
$router->get('/api/catalog', fn() => $catalogCtrl->autocomplete());
$router->get('/export/csv', fn() => $auditCtrl->exportCsv());
$router->get('/export/bridge', fn() => $auditCtrl->exportBridgeCsv());

$router->get('/debug/health', function () use ($config, $pdo) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "sqlite_path: {$config['sqlite_path']}\n";
    $rows = $pdo->query("PRAGMA database_list;")->fetchAll(PDO::FETCH_ASSOC);
    echo "PRAGMA database_list:\n";
    foreach ($rows as $r) echo "- {$r['name']}: {$r['file']}\n";
    echo "\nColumns in audit_entries:\n";
    $cols = $pdo->query("PRAGMA table_info(audit_entries)")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($cols as $c) echo "- {$c['name']} ({$c['type']})\n";
});
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);


