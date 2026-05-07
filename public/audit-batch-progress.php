<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Models\AuditBatchItem;
use App\Models\AuditBatchSession;

$sessionId = (int) ($_GET['session_id'] ?? 0);

if ($sessionId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Sessão inválida']);
    exit;
}

$pdo = new PDO(
    'sqlite:' . __DIR__ . '/../database/tickets.db',
    null,
    null,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$itemModel = new AuditBatchItem($pdo);
$sessionModel = new AuditBatchSession($pdo);

// calcula progresso
$progress = $itemModel->getSessionProgress($sessionId);

// ✅ regra de finalização automática
if ((int)$progress['remaining'] === 0) {
    $sessionModel->markAsFinished($sessionId);
}

header('Content-Type: application/json');
echo json_encode($progress);