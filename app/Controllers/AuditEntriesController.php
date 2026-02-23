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
        $this->logger ??= class_exists(Logger::class) ? new Logger() : null;
    }

    private function getBasePath(): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
        return ($dir === '/' || $dir === '.') ? '' : $dir;
    }

    private function render(string $viewName, array $data = []): void
    {
        $baseDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Views';
        $layout  = $baseDir . DIRECTORY_SEPARATOR . 'layout.php';
        $view    = $viewName;
        $base    = $this->getBasePath();

        if (!empty($data)) {
            extract($data, EXTR_OVERWRITE);
        }

        if (!is_file($layout)) {
            http_response_code(500);
            $safe = htmlspecialchars($layout, ENT_QUOTES, 'UTF-8');
            echo "<div style='color:#b91c1c;background:#fee2e2;padding:10px;border:1px solid #ef4444;border-radius:6px'>
                    Layout não encontrado: <code>{$safe}</code>
                  </div>";
            return;
        }

        require $layout;
    }

    private function sanitizeOld(array $post): array
    {
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

    private function isTicketNumberDuplicate(\PDOException $e): bool
    {
        $msg  = strtolower($e->getMessage() ?? '');
        $info = $e->errorInfo ?? null;

        if (str_contains($msg, 'unique constraint failed')
            && (str_contains($msg, 'audit_entries.ticket_number') || str_contains($msg, 'ticket_number'))) {
            return true;
        }
        if (is_array($info) && (int)($info[1] ?? 0) === 1062) {
            return true;
        }
        if (($info[0] ?? null) === '23505') {
            return true;
        }
        return false;
    }

    public function showForm(): void
    {
        $this->render('form', [
            'title' => 'Auditoria de Chamados',
            'error' => null,
            'old'   => [],
        ]);
    }

    public function form(): void { $this->showForm(); }

    public function store(): void
    {
        $post   = $_POST ?? [];
        $logger = $this->logger;

        $raw = (string)($post['noncompliance_reason_ids'] ?? '');
        $ids = preg_split('/[;,|\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ids = array_values(array_unique(array_filter(
            array_map(static fn($x) => (int)preg_replace('/\D+/', '', $x), $ids),
            static fn($n) => $n > 0
        )));
        $post['noncompliance_reason_ids'] = implode(';', $ids);

        $isNc = (string)($post['is_compliant'] ?? '1') === '0';
        if ($isNc && empty($ids)) {
            http_response_code(422);
            $this->render('form', [
                'title' => 'Auditoria de Chamados',
                'error' => 'Selecione ao menos uma justificativa.',
                'old'   => $this->sanitizeOld($post),
            ]);
            return;
        }

        try {
            $id = $this->service->handle($post);
            $logger?->write('debug.log', date('c') . " OK id={$id}" . PHP_EOL);

            $base = $this->getBasePath();
            header('Location: ' . $base . '/?created=' . urlencode((string)$id), true, 303);
            exit;

        } catch (\InvalidArgumentException $e) {
            http_response_code(422);
            $this->render('form', [
                'title' => 'Auditoria de Chamados',
                'error' => $e->getMessage(),
                'old'   => $this->sanitizeOld($post),
            ]);
            return;

        } catch (\PDOException $e) {
            $logger?->write(
                'debug.log',
                date('c') . " PDOEX: code={$e->getCode()} info=" . print_r($e->errorInfo, true)
                . " msg=" . $e->getMessage() . PHP_EOL
            );

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
                'old'   => $this->sanitizeOld($post),
            ]);
            return;
        }
    }

    public function exportCsv(): void
    {
        $prevErrorReporting = error_reporting();
        $prevDisplayErrors  = ini_get('display_errors');
        error_reporting($prevErrorReporting & ~E_DEPRECATED);
        ini_set('display_errors', '0');

        while (ob_get_level() > 0) { @ob_end_clean(); }

        try {
            $month = isset($_GET['audit_month']) ? trim((string)$_GET['audit_month']) : null;

            $rows = $this->repo->exportRows([
                'audit_month' => $month ?: null
            ]);

            $filename = 'auditoria_chamados_' . ($month && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : 'base') . '.csv';

            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="'.$filename.'"');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');

            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8

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
