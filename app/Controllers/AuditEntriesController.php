<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\CreateAuditEntryService;
use App\Repositories\AuditEntryRepository;

final class AuditEntriesController
{
    public function __construct(
        private CreateAuditEntryService $service,
        private AuditEntryRepository $repo,
        private ?\App\Support\Logger $logger = null
    ) {
        $this->logger ??= class_exists(\App\Support\Logger::class) ? new \App\Support\Logger() : null;
    }

    private function base(): string
    {
        $s = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $d = rtrim(str_replace('\\', '/', dirname($s)), '/');
        return ($d === '/' || $d === '.') ? '' : $d;
    }

    private function render(string $view, array $data = []): void
    {
        $baseDir = dirname(__DIR__) . '/Views';
        $layout  = $baseDir . '/layout.php';
        $base    = $this->base();
        if (!empty($data)) extract($data, EXTR_OVERWRITE);
        require $layout;
    }

    /** Página do formulário */
    public function form(): void
    {
        $old = [];
        // Prefill de conveniência
        if (!empty($_SESSION['user']['name'])) {
            $old['requester_name'] = (string)$_SESSION['user']['name'];
        }
        // Sinaliza travamento e valor do auditor
        if (!empty($_SESSION['user']['id']) && !empty($_SESSION['user']['name'])) {
            $old['kyndryl_auditor']     = (string)$_SESSION['user']['name'];
            $old['kyndryl_auditor_id']  = (int)$_SESSION['user']['id'];
            $old['_lock_kyndryl_field'] = 1;
        }

        $this->render('form', [
            'title' => 'Auditoria de Chamados',
            'error' => null,
            'old'   => $old,
        ]);
    }

    /** Recebe o POST e salva */
    public function store(): void
{
    $post   = $_POST ?? [];
    $logger = $this->logger;

    // 🔐 Blindagem: força user_id / kyndryl_auditor com base na sessão
    if (!empty($_SESSION['user']['id']) && !empty($_SESSION['user']['name'])) {
        $post['user_id']            = (int)$_SESSION['user']['id'];   // << VÍNCULO AQUI
        $post['kyndryl_auditor_id'] = (int)$_SESSION['user']['id'];
        $post['kyndryl_auditor']    = (string)$_SESSION['user']['name'];
    } else {
        unset($post['user_id'], $post['kyndryl_auditor_id']);
    }

    // Normaliza os IDs de justificativa (string -> "10;5;1")
    $raw = (string)($post['noncompliance_reason_ids'] ?? '');
    $ids = preg_split('/[;,|\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $ids = array_values(array_unique(array_filter(
        array_map(static fn($x) => (int)preg_replace('/\D+/', '', $x), $ids),
        static fn($n) => $n > 0
    )));
    $post['noncompliance_reason_ids'] = implode(';', $ids);

    // Regra: "Não conforme" exige pelo menos 1 justificativa
    $isNc = (string)($post['is_compliant'] ?? '1') === '0';
    if ($isNc && empty($ids)) {
        http_response_code(422);
        $this->render('form', [
            'title' => 'Auditoria de Chamados',
            'error' => 'Selecione ao menos uma justificativa.',
            'old'   => $post,
        ]);
        return;
    }

    try {
        $id = $this->service->handle($post);

        // (Opcional) PRG - redireciona para remover URL de POST
        // $base = $this->base();
        // header('Location: ' . $base . '/?created=' . urlencode((string)$id), true, 303);
        // exit;

        $logger?->write('debug.log', date('c') . " OK id={$id}" . PHP_EOL);
        $this->render('success', ['title' => 'Salvo', 'id' => $id]);
        return;

    } catch (\InvalidArgumentException $e) {
        http_response_code(422);
        $this->render('form', [
            'title' => 'Auditoria de Chamados',
            'error' => $e->getMessage(),
            'old'   => $post,
        ]);
        return;

    } catch (\PDOException $e) {
        $detail = $e->errorInfo[2] ?? $e->getMessage();

        if ($this->isTicketNumberDuplicate($e)) {
            $ticket = (string)($post['ticket_number'] ?? '');
            $msg = $ticket !== '' ? "{$ticket} já está salvo." : "Este Número de Ticket já está salvo.";
        } elseif (stripos($detail, 'FOREIGN KEY constraint failed') !== false) {
            $msg = 'Falha de integridade: alguma justificativa/entrada não existe. (' . $detail . ')';
        } elseif (stripos($detail, 'CHECK constraint failed') !== false) {
            $msg = 'Regra de validação do banco violada. (' . $detail . ')';
        } elseif (str_contains($detail, 'NOT NULL constraint failed')) {
            $msg = 'Campo obrigatório ausente. (' . $detail . ')';
        } else {
            $msg = 'Não foi possível salvar: ' . $detail;
        }

        http_response_code(422);
        $this->render('form', [
            'title' => 'Auditoria de Chamados',
            'error' => $msg,
            'old'   => $post,
        ]);
        return;
    }
}

    /** Exporta CSV */
    public function exportCsv(): void
    {
        $prev = error_reporting();
        $old  = ini_get('display_errors');
        error_reporting($prev & ~E_DEPRECATED);
        ini_set('display_errors', '0');

        while (ob_get_level() > 0) @ob_end_clean();

        try {
            $month = isset($_GET['audit_month']) ? trim((string)$_GET['audit_month']) : null;
            $rows = $this->repo->exportRows(['audit_month' => $month ?: null]);

            $filename = 'auditoria_chamados_' .
                ($month && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : 'base') . '.csv';

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="'.$filename.'"');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');

            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            foreach ($rows as $r) {
                fputcsv($out, array_values($r), ';', '"', '\\', "\r\n");
            }

            fclose($out);
            exit;

        } finally {
            ini_set('display_errors', $old);
            error_reporting($prev);
        }
    }

    /* ================= Helpers ================= */

    private function isTicketNumberDuplicate(\PDOException $e): bool
    {
        $msg  = strtolower($e->getMessage() ?? '');
        $info = $e->errorInfo ?? null;

        if (str_contains($msg, 'unique constraint failed')
            && (str_contains($msg, 'audit_entries.ticket_number') || str_contains($msg, 'ticket_number'))) {
            return true;
        }
        if (is_array($info) && (int)($info[1] ?? 0) === 1062) return true; // MySQL
        if (($info[0] ?? null) === '23505') return true;                   // PostgreSQL
        return false;
    }
}