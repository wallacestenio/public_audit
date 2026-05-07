<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Models\AuditBatchItem;

// -------------------------------------------------
// Validar item_id
// -------------------------------------------------
$itemId = (int) ($_POST['item_id'] ?? 0);

if ($itemId <= 0) {
    http_response_code(400);
    echo 'Item inválido';
    exit;
}

// -------------------------------------------------
// PDO manual
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
// Atualizar status
// -------------------------------------------------
$itemModel->updateStatus($itemId, 'saved');

echo 'ok';