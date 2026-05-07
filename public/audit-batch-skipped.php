<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Models\AuditBatchItem;

$sessionId = (int) ($_GET['session_id'] ?? 0);

if ($sessionId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Sessão inválida']);
    exit;
}

$itemModel = new AuditBatchItem($pdo);

$items = $itemModel->getSkippedItemsBySession($sessionId);

header('Content-Type: application/json');
echo json_encode($items);