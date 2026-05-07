<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/bootstrap.php';

use App\Models\AuditBatchItem;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Auditoria - Sessão '.$sessionId);

// Cabeçalho
$sheet->fromArray([
    'Item',
    'Prioridade',
    'Risco',
    'SLA Atendido',
    'Resumo APKIA',
    'Texto do Chamado',
    'Data da Revisão'
], null, 'A1');

$row = 2;

foreach ($items as $item) {
    $apkia = json_decode($item['apkia_result'], true);
    $summary = isset($apkia['summary'])
        ? implode("\n", $apkia['summary'])
        : '';

    $sheet->fromArray([
        $item['item_index'],
        $item['attention_level'],
        $item['risk_score'],
        $item['sla_met'] ? 'Sim' : 'Não',
        $summary,
        $item['raw_text'],
        $item['reviewed_at'],
    ], null, "A{$row}");

    $row++;
}

// Ajuste de colunas
foreach (range('A','G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header(
    'Content-Disposition: attachment; filename="auditoria_sessao_'.$sessionId.'.xlsx"'
);

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');