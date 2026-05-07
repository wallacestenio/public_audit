<?php
declare(strict_types=1);

// Autoload
require_once __DIR__ . '/../app/bootstrap.php';

use App\Models\AuditBatchSession;

// ✅ cria PDO manualmente (mock-first, sem container)
$pdo = new PDO(
    'sqlite:' . __DIR__ . '/../database/tickets.db',
    null,
    null,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]
);

echo '<pre>';

// ✅ instancia o model
$sessionModel = new AuditBatchSession($pdo);

// ✅ cria a sessão
$sessionId = $sessionModel->create(
    createdBy: 1,
    sourceType: 'mock',
    sourceName: 'primeira sessão em lote'
);

echo "Sessão criada com ID: {$sessionId}\n\n";

// ✅ busca a sessão criada
$session = $sessionModel->findById($sessionId);

// ✅ mostra o resultado
var_dump($session);

echo '</pre>';
