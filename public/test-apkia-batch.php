<?php
declare(strict_types=1);

// Autoload
require_once __DIR__ . '/../app/bootstrap.php';

use App\Models\AuditBatchSession;
use App\Models\AuditBatchItem;
use App\Services\APKIA\APKIAService;
use App\Services\APKIA\APKIABatchService;

// -------------------------------------------------
// PDO manual (mock-first, sem container)
// -------------------------------------------------
$pdo = new PDO(
    'sqlite:' . __DIR__ . '/../database/tickets.db',
    null,
    null,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]
);

echo '<pre>';

// -------------------------------------------------
// 1️⃣ Criar sessão
// -------------------------------------------------
$sessionModel = new AuditBatchSession($pdo);
$itemModel    = new AuditBatchItem($pdo);

$sessionId = $sessionModel->create(
    createdBy: 1,
    sourceType: 'mock',
    sourceName: 'teste APKIA batch'
);

echo "Sessão criada: ID {$sessionId}\n\n";

// -------------------------------------------------
// 2️⃣ Criar itens
// -------------------------------------------------
$itemModel->addItem(
    $sessionId,
    1,
    'Chamado INC200001: SLA estourado, atraso significativo'
);

$itemModel->addItem(
    $sessionId,
    2,
    'Chamado INC200002: Atendimento concluído dentro do prazo'
);

$itemModel->addItem(
    $sessionId,
    3,
    'Chamado TASK30001: Evidência insuficiente, possível falha grave'
);

echo "Itens criados para a sessão.\n\n";

// -------------------------------------------------
// 3️⃣ Executar APKIA em lote
// -------------------------------------------------
$apkiaService = new APKIAService($pdo);
$batchService = new APKIABatchService($apkiaService, $itemModel);

$batchService->processSession($sessionId);

echo "APKIABatchService executado.\n\n";

// -------------------------------------------------
// 4️⃣ Buscar itens após análise
// -------------------------------------------------
echo "Resultado dos itens após análise APKIA:\n";

$items = $itemModel->findBySession($sessionId);
var_dump($items);

echo '</pre>';