<?php


ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);






require_once __DIR__ . '/../app/bootstrap.php';

use ZipStream\ZipStream;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Composer\Pcre\Preg;


use Src\Database;

use App\Core\Router;

use App\Controllers\ExecutionPlanController;

use App\Controllers\AssistantController;

use App\Services\APKIA\APKIAService;

use App\Controllers\AuditController;
use App\Controllers\APKIAController;

use App\Controllers\ApkiaFlowController;

use App\Controllers\AuditSessionController;

use App\Models\{
    Presets,
    AuditEntry,
    SchemaInspector
};

use App\Repositories\{
    CatalogRepository,
    AuditEntryRepository,
    KyndrylAuditorRepository,
    ImportBatchRepository,
    LocationRepository,
    PetrobrasInspectorRepository,
    AllowedEmailRepository
};

use App\Services\{
    PayloadMapper,
    CreateAuditEntryService,
    ImportAuditEntriesService,
    ImportAuditEntriesXlsxService,
    InventoryAuditsService   // ✅ ADICIONAR
};

use App\Controllers\{
    CatalogController,
    AuditEntriesController,
    LoginController,
    RegisterController,
    ImportAuditEntriesController,
    InventoryAuditsController   // ✅ ADICIONAR
};

use App\Support\{
    Auth,
    Logger,
    CsvReader,
    XlsxReader
};

ini_set('display_errors', '1');
error_reporting(E_ALL);

/* ===================== SESSÃO ===================== */
session_start();


/* ===================== TESTE DE AUTOLOAD (COMPOSER PCRE) ===================== 
use Composer\Pcre\Preg;

var_dump(class_exists(Preg::class));
exit;
*/


/* ===================== TESTE DE AUTOLOAD ===================== 
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

var_dump(
    class_exists(IOFactory::class),
    class_exists(Spreadsheet::class)
);
exit;

*/



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



try {
    $db  = new Database($config['sqlite_path'], $config['schema_sql']);
    $pdo = $db->pdo();
} catch (\Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Falha ao inicializar o banco de dados.\nErro: {$e->getMessage()}\n";
    exit;
}


/* ===================== ROUTER ===================== */
$router = new Router();


/* ===================== SERVICE FACTORY ===================== */
$serviceFactory = new \App\Core\ServiceFactory();



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




$registerCtrl = new RegisterController(
    $locationRepo,
    $inspectorRepo,
    $allowedEmailRepo,
    $pdo
);

// =====================
// IMPORTAÇÃO DE AUDITORIAS
// =====================

/* ===================== IMPORTAÇÃO DE AUDITORIAS ===================== */



/* ✅ READERS */
$csvReader  = new CsvReader();
$xlsxReader = new XlsxReader();

/* ✅ REPOSITORY JÁ EXISTENTE (NÃO RECRIAR) */
// $auditRepo já foi criado acima corretamente

$importBatchRepository = new ImportBatchRepository($pdo);

/* ✅ SERVICES DE IMPORT (USAM O MESMO CORE DO FORM) */
$importAuditEntriesService = new ImportAuditEntriesService(
    $csvReader,
    $auditRepo,
    $importBatchRepository,
    $kyndrylAuditorRepo,
    $createSvc
);

$importAuditEntriesXlsxService = new ImportAuditEntriesXlsxService(
    $xlsxReader,
    $auditRepo,
    $importBatchRepository,
    $kyndrylAuditorRepo,
    $createSvc
);

/* ✅ CONTROLLER */
$importController = new ImportAuditEntriesController(
    $importAuditEntriesService,
    $importAuditEntriesXlsxService
);





/* ===================== AUTH / LOGIN ===================== */
$auth      = new Auth($pdo);
$loginCtrl = new LoginController($auth);

/* ===================== SERVICE FACTORY (EXEMPLO DE USO) ===================== */
$router->get('/apkia/context', function () use ($auth, $pdo) {
    if (!$auth->isAdmin()) {
        http_response_code(403);
        exit('Acesso negado.');
    }

    $controller = new App\Controllers\APKIAController($pdo);
    $controller->showContext();
});

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

// ===================== ROTA DE AUDIT SESSION ===================== 
// =====================
// AUDIT SESSION
// =====================

$router->get('/audit-session/view', function () use ($pdo) {
    $controller = new AuditSessionController($pdo);
    $controller->view();
});

$router->post('/audit-session/import', function () use ($pdo) {
    $controller = new AuditSessionController($pdo);
    $controller->import();
});

$router->post('/audit-session/remove-item', function () use ($pdo) {
    $controller = new AuditSessionController($pdo);
    $controller->removeItem();
});

$router->get('/audit-session/pending', function () use ($pdo) {
    $controller = new AuditSessionController($pdo);
    $controller->pending();
});


$router->get('/audit/list', function () use ($pdo) {
    $controller = new AuditController($pdo);
    $controller->list();
});


$router->get('/audit/view', function () use ($pdo) {
    $controller = new AuditController($pdo);
    $controller->view();
});



$router->post('/audit/save', function () use ($pdo) {
    $controller = new AuditController($pdo);
    $controller->save();
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

/* ===================== ROTA DE DOWNLOAD DE PDF DO EXECUTION PLAN ===================== */




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
$router->get('/api/catalog', function () use ($mustAuthApi, $catalogCtrl) {
    $mustAuthApi();

    // ✅ TEMPORÁRIO: libera autocomplete sem token
    // $validateFormOriginForApi();

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

$router->get('/export/scenario', function () use ($mustAuth, $auditCtrl) {
    $mustAuth();
    $auditCtrl->exportScenarioHtml();
});

/* Export XLSX */

$router->get('/export/xlsx', function () use ($mustAuth, $auditCtrl) {
    $mustAuth();
    $auditCtrl->exportXlsx();
});

/* ===================== NOVAS ROTAS DE AUDITORIA DE ESTOQUE ===================== */


$router->get('/inventory-audits/create', function () use ($mustAuth, $pdo) {
    $mustAuth();

    $controller = new InventoryAuditsController(
        new InventoryAuditsService($pdo),
        new CatalogRepository($pdo),
        new LocationRepository($pdo)
    );

    $controller->create();
});

/* ===================== NOVA ROTA DE SALVAR AUDITORIA DE ESTOQUE ===================== */

$router->post('/inventory-audits', function () use ($mustAuth, $pdo) {
    $mustAuth();

    $controller = new InventoryAuditsController(
        new InventoryAuditsService($pdo),
        new CatalogRepository($pdo),
        new LocationRepository($pdo)
    );

    $controller->store();
});

/* ===================== ROTAS DE EXECUTION PLAN ===================== */

$router->get('/execution-plans', function () use ($auth, $pdo) {
    if (!$auth->isAdmin()) {
        header('Location: ' . base_path() . '/');
        exit;
    }

    $controller = new ExecutionPlanController($pdo);
    $controller->index();
});

$router->get('/execution-plans/create', function () use ($auth, $pdo) {
    if (!$auth->isAdmin()) {
        header('Location: ' . base_path() . '/');
        exit;
    }

    $controller = new ExecutionPlanController($pdo);
    $controller->create();
});

$router->post('/execution-plans/store', function () use ($auth, $pdo) {
    if (!$auth->isAdmin()) {
        header('Location: ' . base_path() . '/');
        exit;
    }

    $controller = new ExecutionPlanController($pdo);
    $controller->store();
});

/* ===================== ROTA DE ATIVAÇÃO DE EXECUTION PLAN ===================== */

$router->post('/execution-plans/activate', function () use ($auth, $pdo) {
    if (!$auth->isAdmin()) {
        header('Location: /');
        exit;
    }

    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        header('Location: /execution-plans');
        exit;
    }

    $controller = new ExecutionPlanController($pdo);
    $controller->activate($id);
});

/* ===================== ROTA DE DOWNLOAD DE PDF DO EXECUTION PLAN ===================== */
$router->get('/execution-plans/pdf', function () use ($auth, $pdo) {
    if (!$auth->isAdmin()) {
        http_response_code(403);
        exit('Acesso negado.');
    }

    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        http_response_code(400);
        exit('Plano inválido.');
    }

    $controller = new App\Controllers\ExecutionPlanController($pdo);
    $controller->downloadPdf($id);
});

// ===================== ROTA DE EXIBIÇÃO DO CONTEXTO DE AUDITORIA DE ESTOQUE =====================
$router->get('/inventory-audits', function () use ($mustAuth, $pdo) {
    $mustAuth();

    $controller = new InventoryAuditsController(
        new InventoryAuditsService($pdo),
        new CatalogRepository($pdo),
        new LocationRepository($pdo)
    );

    $controller->index();
});

/* ===================== ROTA DE EXIBIÇÃO DO CONTEXTO DE AUDITORIA DE ESTOQUE ===================== */
$router->post('/assistant/analyze', [AssistantController::class, 'analyze']);




/* ===================== ROTA DE EXIBIÇÃO DO FORMULÁRIO DE AUDITORIA DE ESTOQUE ===================== */


$router->get('/audit/form', function () use ($pdo) {
    $controller = new AuditController($pdo);
    $controller->form();
});


$router->post('/audit/confirm', function () use ($pdo) {
    $controller = new AuditController($pdo);
    $controller->confirm();
});


/* ===================== ROTA DE PROCESSAMENTO DO FORMULÁRIO DE AUDITORIA DE ESTOQUE ===================== */

$router->post('/audit/save', function () use ($pdo) {
    $controller = new AuditController($pdo);
    $controller->save();
}); 




/* ===================== ROTA DE ANÁLISE DO APKIA ===================== */
$router->post('/apkia/analyze', function () use ($mustAuth, $pdo) {
    $mustAuth();
    (new \App\Controllers\APKIAController($pdo))->analyze();
});

/* ===================== NOVA ROTA DE FLUXO COMPLETO DO APKIA (FORM → ANÁLISE → REDIRECIONA PARA FORM DE AUDITORIA) ===================== */

$router->get('/apkia', function () use ($mustAuth, $pdo) {
    $mustAuth();
    (new ApkiaFlowController($pdo))->form();
});

$router->post('/apkia/process', function () use ($mustAuth, $pdo) {
    $mustAuth();
    (new ApkiaFlowController($pdo))->process();
});





// ===================== NORMALIZA URI =====================

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

$uri = $_SERVER['REQUEST_URI'] ?? '/';

// remove query string (?id=...)
$uri = parse_url($uri, PHP_URL_PATH);

// REMOVE PREFIXO FÍSICO "/8080"
if (strpos($uri, '/8080') === 0) {
    $uri = substr($uri, 5); // remove "/8080"
}

// garante que nunca fique string vazia
$uri = $uri ?: '/';




// ===================== DISPATCH =====================

$router->dispatch($method, $uri);
