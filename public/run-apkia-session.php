<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Models\AuditBatchItem;
use App\Services\APKIA\APKIAService;
use App\Services\APKIA\APKIABatchService;

// PDO
$pdo = new PDO(
    'sqlite:' . __DIR__ . '/../database/tickets.db',
    null,
    null,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]
);

$itemModel = new AuditBatchItem($pdo);
$apkiaService = new APKIAService($pdo);
$batchService = new APKIABatchService($apkiaService, $itemModel);

// ajuste aqui o ID da sessão que você quer processar
$sessionId = 16;

echo "Processando sessão {$sessionId}...\n";

$batchService->processSession($sessionId);

echo "✅ APKIA Batch finalizado.\n";