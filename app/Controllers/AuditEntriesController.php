<?php
declare(strict_types=1);

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

    /* =========================================================
       RENDER HELPER
       ========================================================= */
    private function simpleRender(string $template, array $data = []): void
    {
        $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views';
        $file    = $baseDir . DIRECTORY_SEPARATOR . trim($template, "/\\") . '.php';

        if (!is_file($file)) {
            http_response_code(500);
            $safe = htmlspecialchars($file, ENT_QUOTES, 'UTF-8');
            echo "<div style='color:#b91c1c;background:#fee2e2;padding:10px;border:1px solid #ef4444;border-radius:6px'>
                    Template não encontrado: <code>{$safe}</code>
                  </div>";
            return;
        }

        if (!empty($data)) {
            extract($data, EXTR_OVERWRITE);
        }
        require $file;
    }

    private function sanitizeOld(array $post): array
    {
        // Campos que a view espera repovoar
        $fields = [
            'ticket_number','ticket_type',
            'kyndryl_auditor','kyndryl_auditor_id',
            'petrobras_inspector','petrobras_inspector_id',
            'audited_supplier','audited_supplier_id',
            'location','location_id',
            'audit_month','priority','requester_name',
            'category','category_id',
            'resolver_group','resolver_group_id',
            'sla_met','is_compliant','noncompliance_reason_ids'
        ];

        $old = [];
        foreach ($fields as $f) {
            $old[$f] = isset($post[$f]) ? (is_scalar($post[$f]) ? (string)$post[$f] : '') : '';
        }
        return $old;
    }

    private function humanizePdoError(\PDOException $e, array $post): string
    {
        $message = $e->getMessage() ?? '';
        $info    = $e->errorInfo ?? null;
        $tk      = trim((string)($post['ticket_number'] ?? ''));

        // SQLite: UNIQUE constraint failed / MySQL 1062 / Postgres 23505
        if (
            stripos($message, 'unique') !== false ||
            stripos($message, 'duplicate') !== false ||
            (is_array($info) && (string)($info[0] ?? '') === '23000')
        ) {
            return $tk !== ''
                ? "O ticket {$tk} já existe. Altere o número antes de enviar."
                : "Este ticket já existe. Altere o número antes de enviar.";
        }
        return 'Não foi possível salvar no momento. Tente novamente.';
    }

    private function isTicketNumberDuplicate(\PDOException $e): bool
    {
        $msg  = strtolower($e->getMessage() ?? '');
        $info = $e->errorInfo ?? null;

        // SQLite
        if (str_contains($msg, 'unique constraint failed')
            && (str_contains($msg, 'audit_entries.ticket_number') || str_contains($msg, 'ticket_number'))) {
            return true;
        }
        // MySQL
        if (is_array($info) && (int)($info[1] ?? 0) === 1062) {
            return true;
        }
        // Postgres
        if (($info[0] ?? null) === '23505') {
            return true;
        }
        return false;
    }

    private function isIntegrityViolation(\PDOException $e): bool
    {
        if ($e->getCode() === '23000') return true;
        $info = $e->errorInfo ?? null;
        // SQLite: 19 (constraint violation genérica)
        if (is_array($info) && (int)($info[1] ?? 0) === 19) return true;
        // Postgres: not_null, fk, check
        if (in_array(($info[0] ?? ''), ['23502','23503','23514'], true)) return true;
        return false;
    }

    /* =========================================================
       ROTAS: GET FORM
       ========================================================= */
    /** Alias usado no roteador simples (ex.: GET /) */
    public function showForm(): void
    {
        $this->simpleRender('form', [
            'title' => 'Formulário de Chamados',
            'error' => null,
            'old'   => [],
        ]);
    }

    /** Caso sua rota aponte para form(), deixo o alias também */
    public function form(): void
    {
        $this->showForm();
    }

    /* =========================================================
       ROTAS: POST CRIAÇÃO
       ========================================================= */
    public function store(): void
    {
        $post   = $_POST ?? [];
        $logger = $this->logger;

        // Normaliza os IDs de justificativas (aceita ; , | e espaços)
        $raw = (string)($post['noncompliance_reason_ids'] ?? '');
        $ids = preg_split('/[;,|\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ids = array_values(array_unique(array_filter(
            array_map(static fn($x) => (int)preg_replace('/\D+/', '', $x), $ids),
            static fn($n) => $n > 0
        )));
        $post['noncompliance_reason_ids'] = implode(';', $ids);

        // Se marcado "Não conforme" (0), exige ao menos 1 justificativa
        $isNc = (string)($post['is_compliant'] ?? '1') === '0';
        if ($isNc && empty($ids)) {
            http_response_code(422);
            $this->simpleRender('form', [
                'title' => 'Formulário de Chamados',
                'error' => 'Selecione ao menos uma justificativa.',
                'old'   => $this->sanitizeOld($post),
            ]);
            return;
        }

        try {
            $id = $this->service->handle($post);
            $logger?->write('debug.log', date('c') . " OK id={$id}" . PHP_EOL);

            // SUCESSO -> volta para o FORM (GET) com ?created=ID (UX correto)
            $formRoute = '/'; // ajuste se seu GET for outro caminho
            header('Location: ' . $formRoute . '?created=' . urlencode((string)$id), true, 303);
            exit;

        } catch (\InvalidArgumentException $e) {
            // Erro de validação (service) -> manter no form com old + msg
            http_response_code(422);
            $this->simpleRender('form', [
                'title' => 'Formulário de Chamados',
                'error' => $e->getMessage(),
                'old'   => $this->sanitizeOld($post),
            ]);
            return;

        } catch (\PDOException $e) {
            // Log detalhado
            $logger?->write(
                'debug.log',
                date('c') . " PDOEX: code={$e->getCode()} info=" . print_r($e->errorInfo, true)
                . " msg=" . $e->getMessage() . PHP_EOL
            );

            // Duplicidade de ticket
            if ($this->isTicketNumberDuplicate($e)) {
                http_response_code(422); // manter 422 para UX uniforme
                $ticket = (string)($post['ticket_number'] ?? '');
                $msg = $ticket !== '' ? "{$ticket} já está salvo." : "Este Número de Ticket já está salvo.";
                $this->simpleRender('form', [
                    'title' => 'Formulário de Chamados',
                    'error' => $msg,
                    'old'   => $this->sanitizeOld($post),
                ]);
                return;
            }

            // Outras violações / erros
            $msg    = $e->getMessage();
            $detail = $e->errorInfo[2] ?? $msg;
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
                'error' => $error,
                'old'   => $this->sanitizeOld($post),
            ]);
            return;
        }
    }

    /* =========================================================
       EXPORTAR CSV
       ========================================================= */
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
            $month = isset($_GET['audit_month']) ? trim((string)$_GET['audit_month']) : null;

            // Busca linhas já normalizadas pelo Repository
            $rows = $this->repo->exportRows([
                'audit_month' => $month ?: null
            ]);

            // Nome do arquivo
            $filename = 'auditoria_chamados_';
            if ($month && preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $month)) {
                $filename .= $month . '.csv';
            } else {
                $filename .= 'base.csv';
            }

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="'.$filename.'"');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');

            $out = fopen('php://output', 'w');

            // BOM UTF‑8 para Excel
            fwrite($out, "\xEF\xBB\xBF");

            // CSV estilo Brasil/Excel
            $sep       = ';';
            $enclosure = '"';
            $escape    = '\\';
            $eol       = "\r\n";

            foreach ($rows as $r) {
                fputcsv($out, array_values($r), $sep, $enclosure, $escape, $eol);
            }

            fclose($out);
            exit;
        } finally {
            ini_set('display_errors', $prevDisplayErrors);
            error_reporting($prevErrorReporting);
        }
    }
}