<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Models\AuditBatchItem;

// -------------------------------------------------
// Validar session_id
// -------------------------------------------------
$sessionId = (int) ($_GET['session_id'] ?? 0);

if ($sessionId <= 0) {
    http_response_code(400);
    echo 'Sessão inválida';
    exit;
}

// -------------------------------------------------
// PDO manual (mesmo padrão dos testes)
// -------------------------------------------------
$pdo = new PDO(
    'sqlite:' . __DIR__ . '/../database/tickets.db',
    null,
    null,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]
);

$itemModel = new AuditBatchItem($pdo);

// -------------------------------------------------
// Buscar próximo item priorizado
// -------------------------------------------------
$item = $itemModel->getNextItem($sessionId);

if ($item && $item['status'] === 'pending') {
    $itemModel->updateStatus((int)$item['id'], 'reviewing');
}

header('Content-Type: application/json');
echo json_encode($item);