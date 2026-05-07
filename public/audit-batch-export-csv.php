<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Models\AuditBatchItem;

$sessionId = (int)($_GET['session_id'] ?? 0);
if ($sessionId <= 0) {
    http_response_code(400);
    echo 'Sessão inválida';
    exit;
}

$pdo = new PDO(
    'sqlite:' . __DIR__ . '/../database/tickets.db',
    null,
    null,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$itemModel = new AuditBatchItem($pdo);
$items = $itemModel->getSavedItemsForExport($sessionId);

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="auditoria_sessao_'.$sessionId.'.csv"');

$out = fopen('php://output', 'w');

// Cabeçalho
fputcsv($out, [
    'Item',
    'Prioridade',
    'Risco',
    'SLA Atendido',
    'Resumo APKIA',
    'Texto do Chamado',
    'Data da Revisão'
]);

foreach ($items as $item) {
    $apkia = json_decode($item['apkia_result'], true);
    $summary = isset($apkia['summary'])
        ? implode(' | ', $apkia['summary'])
        : '';

    fputcsv($out, [
        $item['item_index'],
        $item['attention_level'],
        $item['risk_score'],
        $item['sla_met'] ? 'Sim' : 'Não',
        $summary,
        $item['raw_text'],
        $item['reviewed_at'],
    ]);
}

fclose($out);