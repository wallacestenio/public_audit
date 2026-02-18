<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Repositories\AuditEntryRepository;
use App\Services\CreateAuditEntryService;
use App\Support\Logger;
use PDOException;

final class AuditEntriesController
{
    public function __construct(
        private CreateAuditEntryService $service,
        private AuditEntryRepository $repo
    ) {}

    /* =======================
       PÁGINA DO FORMULÁRIO
       ======================= */
    public function form(): void
    {
        echo View::render('form', ['title' => 'Formulário de Chamados']);
    }

    /* =======================
       SALVAR (POST /audit-entries)
       ======================= */
    public function store(): void
    {
        $logger = new Logger();
        $post = $_POST ?? [];
        $logger->write('debug.log', date('c')." /audit-entries POST: ".print_r($post,true).PHP_EOL);

        // Para mensagem amigável
        $ticket = isset($post['ticket_number']) ? strtoupper(trim((string)$post['ticket_number'])) : '';

        try {
            $id = $this->service->handle($post);
            $logger->write('debug.log', date('c')." OK id={$id}".PHP_EOL);

            echo View::render('success', ['id' => $id, 'title' => 'Salvo']);
            return;

        } catch (PDOException $e) {

            // Log detalhado
            $logger->write('debug.log', date('c')." PDOEX: code={$e->getCode()} info=".print_r($e->errorInfo,true)." msg=".$e->getMessage().PHP_EOL);

            // Somente quando for UNIQUE em ticket_number
            if ($this->isTicketNumberDuplicate($e)) {
                $msg = $ticket !== '' ? "{$ticket} já está salvo." : "Este Número de Ticket já está salvo.";
                http_response_code(409);
                echo View::render('form', [
                    'title' => 'Formulário de Chamados',
                    'error' => $msg,
                    'old'   => $post
                ]);
                return;
            }

            // Outras violações de integridade (NOT NULL, CHECK, etc.)
            if ($this->isIntegrityViolation($e)) {
                http_response_code(422);
                echo View::render('form', [
                    'title' => 'Formulário de Chamados',
                    'error' => 'Não foi possível salvar: verifique os campos obrigatórios e valores informados.',
                    'old'   => $post
                ]);
                return;
            }

            // Erro desconhecido
            $logger->write('debug.log', date('c')." ERR DB: ".$e->getMessage().PHP_EOL);
            http_response_code(500);
            echo View::render('form', [
                'title' => 'Formulário de Chamados',
                'error' => 'Erro ao salvar. Tente novamente.',
                'old'   => $post
            ]);
            return;

        } catch (\InvalidArgumentException $e) {
            http_response_code(422);
            echo View::render('form', [
                'title' => 'Formulário de Chamados',
                'error' => $e->getMessage(),
                'old'   => $post
            ]);
            return;

        } catch (\Throwable $e) {
            $logger->write('debug.log', date('c')." ERR: ".$e->getMessage().PHP_EOL);
            http_response_code(500);
            echo View::render('form', [
                'title' => 'Formulário de Chamados',
                'error' => 'Erro inesperado. Tente novamente.',
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