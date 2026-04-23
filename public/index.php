<?php
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

/* ===================== SESSÃO ===================== */
session_start();

/* ===================== AUTOLOADER PSR-4 ===================== */
spl_autoload_register(function (string $class): void {
    $prefixes = [
        'App\\' => __DIR__ . '/../app/',
        'Src\\' => __DIR__ . '/../src/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
            continue;
        }
        $relative = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
            return;
        }
    }
});

/* ===================== FUNÇÃO BASE_PATH ===================== */
function base_path(): string {
    $s = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
    $d = rtrim(str_replace('\\', '/', dirname($s)), '/');
    return ($d === '/' || $d === '.') ? '' : $d;
}

/* ===================== CONFIG / DATABASE ===================== */
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
    echo "Falha ao inicializar o banco de dados.\nErro: {$e->getMessage()}\n";
    exit;
}

/* ===================== DEPENDENCIES ===================== */
use App\Core\Router;

use App\Models\{
    Presets,
    AuditEntry,
    SchemaInspector
};

use App\Repositories\{
    CatalogRepository,
    AuditEntryRepository,
    KyndrylAuditorRepository
};

use App\Services\{
    PayloadMapper,
    CreateAuditEntryService
};

use App\Controllers\{
    CatalogController,
    AuditEntriesController,
    LoginController
};

use App\Support\{
    Auth,
    Logger
};


use App\Controllers\RegisterController;
use App\Repositories\LocationRepository;
use App\Repositories\PetrobrasInspectorRepository;
use App\Repositories\AllowedEmailRepository;

//IMPORT FILES XLSX
use App\Controllers\ImportAuditEntriesController;
use App\Services\ImportAuditEntriesService;
use App\Support\CsvReader;
use App\Repositories\ImportBatchRepository;





// repositories
$locationRepo      = new LocationRepository($pdo);
$inspectorRepo     = new PetrobrasInspectorRepository($pdo);
$allowedEmailRepo  = new AllowedEmailRepository($pdo);


/* ===================== ROUTER ===================== */
$router = new Router();


/* ===================== SCHEMA / MAPPER ===================== */
$schema = new SchemaInspector($pdo);
$mapper = new PayloadMapper($schema, $pdo);

/* ===================== CATÁLOGOS ===================== */
$presets     = new Presets($pdo);
$catalogRepo = new CatalogRepository($pdo);
$catalogCtrl = new CatalogController($catalogRepo);

/* ===================== LOGGER ===================== */
$logger = class_exists(Logger::class) ? new Logger() : null;

/* ===================== AUDIT ENTRIES ===================== */
$auditModel = new AuditEntry($pdo);
$auditRepo  = new AuditEntryRepository($auditModel);
$createSvc  = new CreateAuditEntryService($auditRepo);


$locationRepo      = new LocationRepository($pdo);
$inspectorRepo     = new PetrobrasInspectorRepository($pdo);
$allowedEmailRepo  = new AllowedEmailRepository($pdo);


/* ✅ REPOSITÓRIO KYNDRYL AUDITOR */
$kyndrylAuditorRepo = new KyndrylAuditorRepository($pdo);

/* ✅ CONTROLLER AUDIT ENTRIES (CORRETO) */
$auditCtrl = new AuditEntriesController(
    $createSvc,
    $auditRepo,
    $kyndrylAuditorRepo,
    $logger
);


$locationRepo      = new LocationRepository($pdo);
$inspectorRepo     = new PetrobrasInspectorRepository($pdo);
$allowedEmailRepo  = new AllowedEmailRepository($pdo);

$registerCtrl = new RegisterController(
    $locationRepo,
    $inspectorRepo,
    $allowedEmailRepo,
    $pdo
);

// =====================
// IMPORTAÇÃO DE AUDITORIAS
// =====================

$csvReader = new CsvReader();

$importBatchRepository = new ImportBatchRepository($pdo);

$importAuditEntriesService = new ImportAuditEntriesService(
    $csvReader,
    $auditRepo,              // ✅ variável existente
    $importBatchRepository,
    $kyndrylAuditorRepo      // ✅ variável existente
);


$importController = new ImportAuditEntriesController(
    $importAuditEntriesService
);

/* ===================== AUTH / LOGIN ===================== */
$auth      = new Auth($pdo);
$loginCtrl = new LoginController($auth);

/* ===================== GUARDS ===================== */
$mustAuth = function () use ($auth) {
    if (!$auth->check()) {
        header('Location: ' . base_path() . '/login');
        exit;
    }
};

/* ===================== registro / GET ===================== */
$router->get('/register', function () use ($registerCtrl) {
    $registerCtrl->show();
});


/* ===================== registro / POST===================== */

$router->post('/register', function () use ($registerCtrl) {
    $registerCtrl->store();
});



$mustAdmin = function () use ($auth) {
    if (!$auth->isAdmin()) {
        header('Location: ' . base_path() . '/');
        exit;
    }
};

/* ===================== API HELPERS ===================== */
$sendJson = function (int $status, array $payload) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
};

$mustAuthApi = function () use ($auth, $sendJson) {
    if (!$auth->check()) {
        $sendJson(401, ['error' => 'Unauthorized']);
    }
};

$validateFormOriginForApi = function () use ($sendJson) {
    $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    if (strcasecmp($xhr, 'XMLHttpRequest') !== 0) {
        $sendJson(403, ['error' => 'Forbidden']);
    }

    $token = $_SERVER['HTTP_X_FORM_TOKEN'] ?? '';
    $reg   = $_SESSION['form_token_catalog'] ?? null;
    $now   = time();

    if (
        !is_array($reg) ||
        empty($reg['v']) ||
        empty($reg['exp']) ||
        $reg['v'] !== $token ||
        (int)$reg['exp'] < $now
    ) {
        $sendJson(403, ['error' => 'Forbidden']);
    }
};

/* ===================== ROTAS ===================== */

/* Login */
$router->get('/login',  fn() => $loginCtrl->show());
$router->post('/login', fn() => $loginCtrl->login());
$router->post('/logout', fn() => $loginCtrl->logout());

/* Audit Entries */
$router->get('/', function () use ($mustAuth, $auditCtrl) {
    $mustAuth();
    $auditCtrl->form();
});

$router->post('/audit-entries', function () use ($mustAuth, $auditCtrl) {
    $mustAuth();
    $auditCtrl->store();
});

/* API Catálogo */
$router->get('/api/catalog', function () use ($mustAuthApi, $validateFormOriginForApi, $catalogCtrl) {
    $mustAuthApi();
    $validateFormOriginForApi();
    $catalogCtrl->autocomplete();
});

/* Validação de ticket duplicado */
$router->get('/api/validate/ticket', function () use ($mustAuthApi, $validateFormOriginForApi, $auditCtrl) {
    $mustAuthApi();
    $validateFormOriginForApi();
    $auditCtrl->validateTicket();
});

/* grafico de mão conformidades */

$router->get('/stats/noncompliance', function () use ($mustAuth, $auditCtrl) {
    $mustAuth();
    $auditCtrl->noncomplianceStats();
});


/* Export CSV */
$router->get('/export/csv', function () use ($mustAuth, $auditCtrl) {
    $mustAuth();
    $auditCtrl->exportCsv();
});

/* ===================== IMPORT FILE XLSX ===================== */

$router->get('/import', function () use ($importController) {
    $importController->showForm();
});

$router->post('/import', function () use ($importController) {
    $importController->import();
});


/* ===================== DISPATCH ===================== */
$router->dispatch(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_SERVER['REQUEST_URI'] ?? '/'
);

