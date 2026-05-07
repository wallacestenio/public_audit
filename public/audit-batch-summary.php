<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Models\AuditBatchSession;

$sessionId = (int)($_GET['session_id'] ?? 0);

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

$sessionModel = new AuditBatchSession($pdo);
$summary = $sessionModel->getSessionSummary($sessionId);

header('Content-Type: application/json');
echo json_encode($summary);