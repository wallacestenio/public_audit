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
            'title' => 'Formulário de Chamados',
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
    public function exportCsv(): void
    {
        $month = isset($_GET['audit_month']) ? trim((string)$_GET['audit_month']) : null;

        $rows = $this->repo->exportRows([
            'audit_month' => $month ?: null
        ]);

        // Cabeçalho dinâmico
        $header = array_keys($rows[0] ?? [
            'ticket_number'  => null,
            'ticket_type'    => null,
            'audit_month'    => null,
            'priority'       => null,
            'requester_name' => null,
        ]);

        $csvRows = [];
        foreach ($rows as $r) {
            $line = [];
            foreach ($header as $h) $line[] = $r[$h] ?? null;
            $csvRows[] = $line;
        }

        $filename = 'audit_entries' . ($month ? "_{$month}" : '') . '.csv';

        \App\Core\Response::csv($filename, $header, $csvRows);
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