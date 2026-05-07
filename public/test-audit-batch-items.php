<?php
declare(strict_types=1);

// Autoload
require_once __DIR__ . '/../app/bootstrap.php';

use App\Models\AuditBatchSession;
use App\Models\AuditBatchItem;


// ✅ PDO manual (mock-first)
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
// 1️⃣ Criar sessão de auditoria em lote
// -------------------------------------------------
$sessionModel = new AuditBatchSession($pdo);

$sessionId = $sessionModel->create(
    createdBy: 1,
    sourceType: 'mock',
    sourceName: 'teste batch items'
);

echo "Sessão criada: ID {$sessionId}\n\n";

// -------------------------------------------------
// 2️⃣ Criar itens da sessão
// -------------------------------------------------
$itemModel = new AuditBatchItem($pdo);

$itemId1 = $itemModel->addItem(
    $sessionId,
    1,
    'Texto do chamado INC100001 - SLA estourado',
    'INC100001'
);

$itemId2 = $itemModel->addItem(
    $sessionId,
    2,
    'Texto do chamado INC100002 - Dentro do SLA',
    'INC100002'
);

$itemId3 = $itemModel->addItem(
    $sessionId,
    3,
    'Texto do chamado TASK90001 - Avaliar procedimento',
    'TASK90001'
);

echo "Itens criados: {$itemId1}, {$itemId2}, {$itemId3}\n\n";

// -------------------------------------------------
// 3️⃣ Listar itens por sessão
// -------------------------------------------------
echo "Itens da sessão antes de atualizar status:\n";
$items = $itemModel->findBySession($sessionId);
var_dump($items);

// -------------------------------------------------
// 4️⃣ Atualizar status
// -------------------------------------------------
$itemModel->updateStatus($itemId1, 'reviewing');
$itemModel->updateStatus($itemId2, 'saved');

echo "\nStatus atualizado.\n\n";

// -------------------------------------------------
// 5️⃣ Listar novamente
// -------------------------------------------------
echo "Itens da sessão após atualizar status:\n";
$itemsUpdated = $itemModel->findBySession($sessionId);
var_dump($itemsUpdated);

echo '</pre>';
