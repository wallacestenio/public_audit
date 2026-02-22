<?php declare(strict_types=1);

namespace App\Controllers;

use App\Services\CreateAuditEntryService;
use App\Repositories\AuditEntryRepository;
use App\Support\Logger;

final class AuditEntriesController
{
    public function __construct(
        private CreateAuditEntryService $service,
        private AuditEntryRepository $repo,
        private ?Logger $logger = null
    ) {
        $this->logger ??= new Logger();
    }

    private function simpleRender(string $template, array $data = []): void
    {
        $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views';
        $file = $baseDir . DIRECTORY_SEPARATOR . trim($template, "/\\") . '.php';

        if (!is_file($file)) {
            http_response_code(500);
            $safe = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
            echo "<div style='color:#b91c1c;background:#fee2e2;padding:10px;border:1px solid #ef4444;border-radius:6px'>
                    Template não encontrado: <code>{$safe}</code>
                  </div>";
            return;
        }

        if (!empty($data)) {
            extract($data, EXTR_SKIP);
        }
        require $file;
    }

    
public function form(): void
    {
        $this->simpleRender('form', [
            'title' => 'Auditoria de Chamados',
            'old'   => $_GET ?? [],
            'error' => null, // opcional: evita avisos na View
        ]);
    }


    
public function store(): void
{
    $post   = $_POST ?? [];
    $logger = $this->logger;

    /* 🔧 Normaliza os IDs de justificativas vindos do POST
       - Aceita separadores ; , espaço
       - Mantém apenas inteiros > 0
       - Regrava em $post['noncompliance_reason_ids'] como "1;2;3"
    */
    // Normaliza os IDs (aceita ; , espaço) e mantém só inteiros > 0
$raw = (string)($post['noncompliance_reason_ids'] ?? '');
$ids = preg_split('/[;,|\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
$ids = array_values(array_unique(array_filter(
    array_map(static fn($x) => (int)preg_replace('/\D+/', '', $x), $ids),
    static fn($n) => $n > 0
)));
$post['noncompliance_reason_ids'] = implode(';', $ids);

// Se "Não conforme" (0), exigir ao menos 1 justificativa
$isNc = (string)($post['is_compliant'] ?? '1') === '0';
if ($isNc && empty($ids)) {
    http_response_code(422);
    $this->simpleRender('form', [
        'title' => 'Formulário de Chamados',
        'error' => 'Selecione ao menos uma justificativa.',
        'old'   => $post
    ]);
    return;
}




        try {
            $id = $this->service->handle($post);
            $logger->write('debug.log', date('c') . " OK id={$id}" . PHP_EOL);

            // ✅ usar simpleRender
            $this->simpleRender('success', ['id' => $id, 'title' => 'Salvo']);
            return;

        } catch (\InvalidArgumentException $e) {
            http_response_code(422);
            
$data = [
  'ticket_number'       => trim((string)($post['ticket_number'] ?? '')),
  'ticket_type'         => (string)($post['ticket_type'] ?? ''),
  'kyndryl_auditor'     => (string)($post['kyndryl_auditor'] ?? ''),
  'petrobras_inspector' => (string)($post['petrobras_inspector'] ?? ''),
  'audited_supplier'    => (string)($post['audited_supplier'] ?? ''),
  'location'            => (string)($post['location'] ?? ''),
  'audit_month'         => (string)($post['audit_month'] ?? ''), // já vem normalizado pelo front; se tiver mapper, pode usar ele
  'priority'            => (int)($post['priority'] ?? 0),
  'requester_name'      => (string)($post['requester_name'] ?? ''),
  'category'            => (string)($post['category'] ?? ''),
  'resolver_group'      => (string)($post['resolver_group'] ?? ''),
  'sla_met'             => (int)($post['sla_met'] ?? 0),
  'is_compliant'        => (int)($post['is_compliant'] ?? 1),
];

            $this->simpleRender('form', [
                'title' => 'Formulário de Chamados',
                'error' => $e->getMessage(),
                'old'   => $post
            ]);
            return;

        
} catch (\PDOException $e) {
    // Log detalhado do PDO
    $logger->write(
        'debug.log',
        date('c') . " PDOEX: code={$e->getCode()} info=" . print_r($e->errorInfo, true) . " msg=" . $e->getMessage() . PHP_EOL
    );

    // 1) Ticket duplicado (mensagem específica)
    if ($this->isTicketNumberDuplicate($e)) {
        $ticket = (string)($post['ticket_number'] ?? '');
        $msg = $ticket !== '' ? "{$ticket} já está salvo." : "Este Número de Ticket já está salvo.";
        http_response_code(409);
        $this->simpleRender('form', [
            'title' => 'Formulário de Chamados',
            'error' => $msg,
            'old'   => $post
        ]);
        return;
    }

    // 2) Outras violações de integridade ou formato
    
$msg    = $e->getMessage();
$detail = $e->errorInfo[2] ?? $msg; // SQLite coloca a frase da constraint aqui

if (stripos($detail, 'FOREIGN KEY constraint failed') !== false) {
    $error = 'Falha de integridade: alguma justificativa/entrada não existe. (' . $detail . ')';
} elseif (stripos($detail, 'CHECK constraint failed') !== false) {
    $error = 'Regra de validação do banco violada. (' . $detail . ')';
} elseif (str_contains($detail, 'NOT NULL constraint failed')) {
    $error = 'Campo obrigatório ausente. (' . $detail . ')';
} else {
    $error = 'Não foi possível salvar: ' . $detail;
}


    http_response_code(422);
    $this->simpleRender('form', [
        'title' => 'Formulário de Chamados',
        'error' => $error, // agora definido
        'old'   => $post
    ]);
    return;
}

    }


    /* =======================
       EXPORTAR CSV (base)
       ======================= */
    // App\Controllers\AuditEntriesController.php

public function exportCsv(): void
{
    // Blindar warnings/erros só nesta resposta (não vazar no CSV)
    $prevErrorReporting = error_reporting();
    $prevDisplayErrors  = ini_get('display_errors');
    error_reporting($prevErrorReporting & ~E_DEPRECATED);
    ini_set('display_errors', '0');

    // Limpar quaisquer buffers abertos (evita HTML/avisos no CSV)
    while (ob_get_level() > 0) { @ob_end_clean(); }

    try {
        // Filtro opcional: ?audit_month=YYYY-MM
        $month = isset($_GET['audit_month']) ? trim((string)$_GET['audit_month']) : null;

        // Busca as linhas já normalizadas (ordem e colunas certas)
        $rows = $this->repo->exportRows([
            'audit_month' => $month ?: null
        ]);

        // ===== Nome do arquivo =====
        // Se veio mês (YYYY-MM) válido -> auditoria_chamados_YYYY-MM.csv
        // Senão, base inteira por enquanto fixamos 2026 -> auditoria_chamados_2026.csv
        $filename = 'auditoria_chamados_';
        if ($month && preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month)) {
            $filename .= $month . '.csv'; // YYYY-MM
        } else {
            $filename .= '2026.csv';      // base inteira (ano fixo por enquanto)
        }

        // Cabeçalhos HTTP para download
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Abre stream de saída
        $out = fopen('php://output', 'w');

        // 🔹 BOM UTF‑8 para o Excel reconhecer acentuação
        fwrite($out, "\xEF\xBB\xBF");

        // Configurações do CSV (padrão Brasil/Excel)
        $sep       = ';';     // separador ponto-e-vírgula
        $enclosure = '"';
        $escape    = '\\';
        $eol       = "\r\n";  // Excel/Windows-friendly; use "\n" se preferir UNIX

        // Apenas conteúdo (SEM cabeçalho)
        foreach ($rows as $r) {
            fputcsv($out, array_values($r), $sep, $enclosure, $escape, $eol);
        }

        fclose($out);
        exit;
    } finally {
        // Restaurar configurações originais
        ini_set('display_errors', $prevDisplayErrors);
        error_reporting($prevErrorReporting);
    }
}

    /* =======================
       EXPORTAR CSV (ponte)
       ======================= */
    public function exportBridgeCsv(): void
    {
        $rows = $this->repo->reasonsBridge();

        $header = array_keys($rows[0] ?? [
            'audit_entry_id'       => null,
            'noncompliance_reason' => null,
        ]);

        $csvRows = [];
        foreach ($rows as $r) {
            $line = [];
            foreach ($header as $h) $line[] = $r[$h] ?? null;
            $csvRows[] = $line;
        }

        \App\Core\Response::csv('audit_entry_noncompliance_reasons.csv', $header, $csvRows);
    }

    // App\Repositories\AuditEntryRepository.php

public function exportRows(array $filters = []): array
{
    // Campos em ORDEM exata solicitada
    $cols = [
        'ticket_number',
        'ticket_type',
        'kyndryl_auditor',
        'petrobras_inspector',
        'audited_supplier',
        'location',
        'audit_month',
        'priority',
        'requester_name',
        'category',
        'resolver_group',
        'sla_met',
        'is_compliant',
        'noncompliance_reasons',
    ];

    $sql = 'SELECT ' . implode(',', $cols) . ' FROM audit_entries';
    $where = [];
    $params = [];

    // Filtro opcional por mês (YYYY-MM)
    if (!empty($filters['audit_month'])) {
        $where[] = 'audit_month = :audit_month';
        $params[':audit_month'] = (string)$filters['audit_month'];
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    // Ordenação previsível (ajuste se quiser outro critério)
    $sql .= ' ORDER BY rowid ASC';

    // Obter o PDO do model de forma clean (sem Reflection)
    if (!method_exists($this->model, 'getPdo')) {
        // Adicione no Model:
        // public function getPdo(): \PDO { return $this->pdo; }
        throw new \RuntimeException('Model não expõe getPdo(). Crie getPdo() para continuar.');
    }

    $pdo = $this->model->getPdo();
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

    // Garante que só retornamos as colunas desejadas (e na ordem)
    // e converte null -> '' para não sujar o CSV
    $normalized = [];
    foreach ($rows as $r) {
        $line = [];
        foreach ($cols as $c) {
            $v = $r[$c] ?? '';
            if ($v === null) $v = '';
            $line[$c] = (string)$v;
        }
        $normalized[] = $line;
    }

    return $normalized;
}


    /* =======================
       HELPERS PRIVADOS
       ======================= */

    /**
     * Verdadeiro apenas quando a exceção representa DUPLICIDADE de ticket_number.
     * Cobre SQLite / MySQL / PostgreSQL.
     */
    private function isTicketNumberDuplicate(\PDOException $e): bool
    {
        $msg = strtolower($e->getMessage() ?? '');

        // SQLite: "UNIQUE constraint failed: audit_entries.ticket_number"
        if (str_contains($msg, 'unique constraint failed')
            && (str_contains($msg, 'audit_entries.ticket_number') || str_contains($msg, 'ticket_number'))) {
            return true;
        }

        // MySQL: duplicate key (1062)
        $info = $e->errorInfo ?? null;
        if (is_array($info) && (int)($info[1] ?? 0) === 1062) {
            return true;
        }

        // PostgreSQL: unique_violation (23505)
        if (($info[0] ?? null) === '23505') {
            return true;
        }

        return false;
    }

    /** Outras violações de integridade (NOT NULL, CHECK, FK, etc.) */
    private function isIntegrityViolation(\PDOException $e): bool
    {
        // SQLSTATE 23000 cobre diversas violações de integridade
        if ($e->getCode() === '23000') return true;

        $info = $e->errorInfo ?? null;

        // SQLite: driver_code 19 = constraint violation genérica
        if (is_array($info) && isset($info[1]) && (int)$info[1] === 19) return true;

        // Postgres: not_null, fk, check
        if (in_array(($info[0] ?? ''), ['23502','23503','23514'], true)) return true;

        return false;
    }
}