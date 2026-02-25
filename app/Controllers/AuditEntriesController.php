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

    /** Gera/renova token para as chamadas de catálogo do formulário (sessão) */
    private function ensureCatalogFormToken(): string
    {
        $now = time();
        $reg = $_SESSION['form_token_catalog'] ?? null;
        if (is_array($reg) && !empty($reg['v']) && (int)($reg['exp'] ?? 0) > $now) {
            return (string)$reg['v'];
        }
        $token = bin2hex(random_bytes(32)); // 64 chars
        $_SESSION['form_token_catalog'] = [
            'v'   => $token,
            'exp' => $now + 2 * 60 * 60, // 2 horas
        ];
        return $token;
    }

    /** Página do formulário */
    public function form(): void
{
    // FLASH (erro e old) vindos de redirecionamento
    $flashError = $_SESSION['flash_error'] ?? null;
    $flashOld   = $_SESSION['flash_old']   ?? null;
    unset($_SESSION['flash_error'], $_SESSION['flash_old']);

    $old = is_array($flashOld) ? $flashOld : [];

    // Prefill: solicitante = nome do usuário
    if (!empty($_SESSION['user']['name']) && empty($old['requester_name'])) {
        $old['requester_name'] = (string)$_SESSION['user']['name'];
    }
    // Travar "Auditor Kyndryl" com sessão
    if (!empty($_SESSION['user']['id']) && !empty($_SESSION['user']['name'])) {
        $old['kyndryl_auditor']     = $old['kyndryl_auditor']    ?? (string)$_SESSION['user']['name'];
        $old['kyndryl_auditor_id']  = $old['kyndryl_auditor_id'] ?? (int)$_SESSION['user']['id'];
        $old['_lock_kyndryl_field'] = 1;
    }

    // 🔐 Token para a API de catálogos (somente via form)
    $form_token_catalog = $this->ensureCatalogFormToken();

    $this->render('form', [
        'title'              => 'Auditoria de Chamados',
        'error'              => $flashError,
        'old'                => $old,
        'form_token_catalog' => $form_token_catalog,
    ]);
}

/** GET /api/validate/ticket?number=INC123... -> JSON { ok: true, duplicate: bool } */
public function validateTicket(): void
{
    header('Content-Type: application/json; charset=utf-8');

    $number = isset($_GET['number']) ? trim((string)$_GET['number']) : '';
    if ($number === '' || !preg_match('/^(INC|RITM|SCTASK)\d{6,}$/', $number)) {
        echo json_encode(['ok' => true, 'duplicate' => false, 'invalid' => true], JSON_UNESCAPED_UNICODE);
        return;
    }

    $dup = $this->repo->existsTicket($number);
    echo json_encode(['ok' => true, 'duplicate' => $dup], JSON_UNESCAPED_UNICODE);
}

    /** Recebe o POST e salva (sem alterações além do já combinado) */
    public function store(): void
{
    $post   = $_POST ?? [];
    $logger = $this->logger;

    // 🔐 Força user_id/kyndryl_auditor pela sessão
    if (!empty($_SESSION['user']['id']) && !empty($_SESSION['user']['name'])) {
        $post['user_id']            = (int)$_SESSION['user']['id'];
        $post['kyndryl_auditor_id'] = (int)$_SESSION['user']['id'];
        $post['kyndryl_auditor']    = (string)$_SESSION['user']['name'];
    } else {
        unset($post['user_id'], $post['kyndryl_auditor_id']);
    }

    // 🔎 VERIFICA DUPLICIDADE ANTES DE QUALQUER OUTRA COISA
    $ticket = trim((string)($post['ticket_number'] ?? ''));
    if ($ticket !== '' && $this->repo->existsTicket($ticket)) {
        // Salva flash e redireciona PRG para "/"
        $_SESSION['flash_error'] = $ticket . ' já está salvo.';
        $_SESSION['flash_old']   = $post;

        $base = $this->base();
        header('Location: ' . $base . '/?dupe=1', true, 303);
        exit;
    }

    // Normalização das justificativas
    $raw = (string)($post['noncompliance_reason_ids'] ?? '');
    $ids = preg_split('/[;,|\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $ids = array_values(array_unique(array_filter(
        array_map(static fn($x) => (int)preg_replace('/\D+/', '', $x), $ids),
        static fn($n) => $n > 0
    )));
    $post['noncompliance_reason_ids'] = implode(';', $ids);

    $isNc = (string)($post['is_compliant'] ?? '1') === '0';
    if ($isNc && empty($ids)) {
        $_SESSION['flash_error'] = 'Selecione ao menos uma justificativa.';
        $_SESSION['flash_old']   = $post;

        $base = $this->base();
        header('Location: ' . $base . '/?invalid=1', true, 303);
        exit;
    }

    try {
        $id = $this->service->handle($post);
        $logger?->write('debug.log', date('c') . " OK id={$id}" . PHP_EOL);

        // PRG pós-sucesso também é melhor UX (evita repost)
        $base = $this->base();
        header('Location: ' . $base . '/?created=' . urlencode((string)$id), true, 303);
        exit;

    } catch (\InvalidArgumentException $e) {
        $_SESSION['flash_error'] = $e->getMessage();
        $_SESSION['flash_old']   = $post;

        $base = $this->base();
        header('Location: ' . $base . '/?invalid=1', true, 303);
        exit;

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

        $_SESSION['flash_error'] = $msg;
        $_SESSION['flash_old']   = $post;

        $base = $this->base();
        header('Location: ' . $base . '/?invalid=1', true, 303);
        exit;
    }
}

    /** Export CSV (inalterado) */
    public function exportCsv(): void
{
    $prev = error_reporting();
    $old  = ini_get('display_errors');
    error_reporting($prev & ~E_DEPRECATED);
    ini_set('display_errors', '0');

    while (ob_get_level() > 0) @ob_end_clean();

    try {
        // 🔐 Obtém o user_id da sessão (obrigatório)
        $userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
        if ($userId <= 0) {
            // Em tese não ocorrerá pois a rota já está protegida por $mustAuth, mas fica a blindagem
            http_response_code(401);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Não autenticado.\n";
            return;
        }

        // Filtro opcional por mês (mantido como antes)
        $month = isset($_GET['audit_month']) ? trim((string)$_GET['audit_month']) : null;

        // 🔎 Agora passamos também o user_id
        $rows = $this->repo->exportRows([
            'audit_month' => $month ?: null,
            'user_id'     => $userId,
        ]);

        // Nome do arquivo mantido; você pode acrescentar o userId se quiser
        $filename = 'auditoria_chamados_' .
            ($month && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : 'base') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');

        // BOM UTF-8
        fwrite($out, "\xEF\xBB\xBF");

        foreach ($rows as $r) {
            // Mantém o separador ';' como no seu padrão atual
            fputcsv($out, array_values($r), ';', '"', '\\', "\r\n");
        }

        fclose($out);
        exit;

    } finally {
        ini_set('display_errors', $old);
        error_reporting($prev);
    }
}

    private function isTicketNumberDuplicate(\PDOException $e): bool
    {
        $msg  = strtolower($e->getMessage() ?? '');
        $info = $e->errorInfo ?? null;
        if (str_contains($msg, 'unique constraint failed') && str_contains($msg, 'ticket_number')) return true;
        if (is_array($info) && (int)($info[1] ?? 0) === 1062) return true; // MySQL
        if (($info[0] ?? null) === '23505') return true;                   // PostgreSQL
        return false;
    }
}