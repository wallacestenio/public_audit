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
    $pdo = $db->pdo();
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

/* Guards de páginas (redirect) */
$mustAuth  = function () use ($auth) { if (!$auth->check()) { header('Location: ' . base_path() . '/login'); exit; } };
$mustAdmin = function () use ($auth) { if (!$auth->isAdmin()) { header('Location: ' . base_path() . '/'); exit; } };

/* Helpers/Guards para API (JSON) */
$sendJson = function (int $status, array $payload) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};
$mustAuthApi = function () use ($auth, $sendJson) {
    if (!$auth->check()) $sendJson(401, ['error' => 'Unauthorized']);
};
$mustAdminApi = function () use ($auth, $sendJson) {
    if (!$auth->check()) $sendJson(401, ['error' => 'Unauthorized']);
    if (!$auth->isAdmin()) $sendJson(403, ['error' => 'Forbidden']);
};

/** Valida que a chamada veio do formulário (para user) */
$validateFormOriginForApi = function () use ($sendJson) {
    // AJAX header
    $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    if (strcasecmp($xhr, 'XMLHttpRequest') !== 0) {
        $sendJson(403, ['error' => 'Forbidden']);
    }
    // Token
    $token = $_SERVER['HTTP_X_FORM_TOKEN'] ?? '';
    $reg   = $_SESSION['form_token_catalog'] ?? null;
    $now   = time();
    if (!is_array($reg) || empty($reg['v']) || empty($reg['exp']) || $reg['v'] !== $token || (int)$reg['exp'] < $now) {
        $sendJson(403, ['error' => 'Forbidden']);
    }
    // (Opcional) validar Origin/Referer com o mesmo host
    // $host = ($_SERVER['HTTP_HOST'] ?? '');
    // $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    // if ($origin && !str_contains($origin, $host)) $sendJson(403, ['error'=>'Forbidden']);
};

/* Rotas públicas (login) */
$router->get('/login',  fn() => $loginCtrl->show());
$router->post('/login', fn() => $loginCtrl->login());
$router->post('/logout', fn() => $loginCtrl->logout());

/* Rotas protegidas (ambos perfis) */
$router->get('/',               function () use ($mustAuth, $auditCtrl) { $mustAuth(); $auditCtrl->form(); });
$router->post('/audit-entries', function () use ($mustAuth, $auditCtrl) { $mustAuth(); $auditCtrl->store(); });

/* ===================== API de catálogo ===================== */
$router->get('/api/catalog', function () use ($mustAuthApi, $auth, $validateFormOriginForApi, $catalogCtrl) {
    $mustAuthApi(); // exige sessão

    if ($auth->isAdmin()) {
        // Admin pode usar direto (opcional: liberar _debug=1)
        $catalogCtrl->autocomplete();
        return;
    }

    // User comum -> só via formulário (AJAX + token)
    $validateFormOriginForApi();
    // remove _debug para user
    if (isset($_GET['_debug'])) unset($_GET['_debug']);
    $catalogCtrl->autocomplete();
});

/* Export (protegida) */
$router->get('/export/csv', function () use ($mustAuth, $auditCtrl) { $mustAuth(); $auditCtrl->exportCsv(); });

/* Admin (placeholder) */
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
$router->get('/debug/health', function () {
    header('Content-Type: text/plain; charset=utf-8');
    echo "OK\n";
});

/* ============ API de validação de ticket duplicado (JSON) ============ */
/* Requer login; se usar validação de origem do formulário para users, aplique a mesma regra do catálogo */
$router->get('/api/validate/ticket', function () use ($mustAuthApi, $auth, $validateFormOriginForApi, $auditCtrl) {
    $mustAuthApi();
    if (!$auth->isAdmin()) {
        // se você já usa token/headers para chamadas do form, valide origem p/ user
        $validateFormOriginForApi();
    }
    // Reaproveitamos o controller dos audit entries para manter coeso
    $auditCtrl->validateTicket();
});

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');