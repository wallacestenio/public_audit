<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\CreateAuditEntryService;
use App\Repositories\AuditEntryRepository;
use App\Repositories\KyndrylAuditorRepository;
use App\Support\Logger;

final class AuditEntriesController
{
    /* ==========================================================
     * CONSTRUCTOR / DEPENDENCIES
     * ========================================================== */
    public function __construct(
        private CreateAuditEntryService  $service,
        private AuditEntryRepository     $repo,
        private KyndrylAuditorRepository $kyndrylRepo,
        private ?Logger                 $logger = null
    ) {
        $this->logger ??= new Logger();
    }

    /* ==========================================================
     * HELPERS
     * ========================================================== */

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

        if (!empty($data)) {
            extract($data, EXTR_OVERWRITE);
        }

        require $layout;
    }

    /** Token para chamadas AJAX do formulário */
    private function ensureCatalogFormToken(): string
    {
        $now = time();
        $reg = $_SESSION['form_token_catalog'] ?? null;

        if (
            is_array($reg) &&
            !empty($reg['v']) &&
            (int)($reg['exp'] ?? 0) > $now
        ) {
            return (string)$reg['v'];
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION['form_token_catalog'] = [
            'v'   => $token,
            'exp' => $now + 2 * 60 * 60,
        ];

        return $token;
    }

    /* ==========================================================
     * FORM (GET /)
     * ========================================================== */

    public function form(): void
    {
        /* ---------- Flash ---------- */
        $flashError = $_SESSION['flash_error'] ?? null;
        $flashOld   = $_SESSION['flash_old'] ?? null;
        unset($_SESSION['flash_error'], $_SESSION['flash_old']);

        $old = is_array($flashOld) ? $flashOld : [];

        /* ---------- Auditor Kyndryl (sessão) ---------- */
        if (!empty($_SESSION['user']['id']) && !empty($_SESSION['user']['name'])) {
            $old['kyndryl_auditor']     = (string)$_SESSION['user']['name'];
            $old['kyndryl_auditor_id']  = (int)$_SESSION['user']['id'];
            $old['_lock_kyndryl_field'] = 1;
        }

        /* ======================================================
         * AUTOPREENCHIMENTO (BASEADO EM kyndryl_auditors)
         * ====================================================== */
        if (!empty($_SESSION['user']['id'])) {
            $auditorName = (string) $_SESSION['user']['name'];

$ref = $this->kyndrylRepo
    ->getInspectorAndLocationByAuditorName($auditorName);


            if (is_array($ref)) {

                // Inspetor Petrobras
                if (!empty($ref['inspector_id']) && !empty($ref['petrobras_inspector'])) {
                    $old['petrobras_inspector']    = (string)$ref['petrobras_inspector'];
                    $old['petrobras_inspector_id'] = (int)$ref['inspector_id'];
                    $old['_lock_inspector_field']  = 1;
                }

                // Localidade
                if (!empty($ref['location_id']) && !empty($ref['location'])) {
                    $old['location']              = (string)$ref['location'];
                    $old['location_id']           = (int)$ref['location_id'];
                    $old['_lock_location_field']  = 1;
                }
            }
        }

        /* ---------- Token ---------- */
        $form_token_catalog = $this->ensureCatalogFormToken();

        /* ---------- Render ---------- */
        $this->render('form', [
            'title'              => 'Auditoria de Chamados',
            'error'              => $flashError,
            'old'                => $old,
            'form_token_catalog' => $form_token_catalog,
        ]);
    }

 public function noncomplianceStats(): void
{
    // ✅ Usuário logado
    $userId = (int)($_SESSION['user']['id'] ?? 0);
    if ($userId <= 0) {
        header('Location: ' . $this->base() . '/login');
        exit;
    }

    // ✅ Filtro por mês (opcional)
    $month = isset($_GET['month']) && $_GET['month'] !== ''
        ? trim((string)$_GET['month'])
        : null;

    // ✅ BUSCA OS DADOS SOMENTE DO USUÁRIO LOGADO
    $raw = $this->repo->fetchNoncomplianceStatsByUser(
        $userId,
        $month
    );

    $months = $this->repo->listAuditMonthsByUser($userId);

    $counter = [];

    foreach ($raw as $line) {

    // ✅ ignora valores nulos ou vazios
    if (!is_string($line) || trim($line) === '') {
        continue;
    }

    $items = array_filter(array_map('trim', explode(';', $line)));

    foreach ($items as $label) {
        $counter[$label] = ($counter[$label] ?? 0) + 1;
    }
}

    arsort($counter);

    $this->render('noncompliance_stats', [
        'title'         => 'Estatísticas de Não Conformidades',
        'stats'         => $counter,
        'months'        => $months,
        'selectedMonth' => $month,
    ]);
}

    /* ==========================================================
     * API – VALIDATE TICKET
     * ========================================================== */

    public function validateTicket(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $number = trim((string)($_GET['number'] ?? ''));

        if ($number === '' || !preg_match('/^(INC|RITM|SCTASK)\d{6,}$/', $number)) {
            echo json_encode(['ok' => true, 'duplicate' => false, 'invalid' => true]);
            return;
        }

        $dup = $this->repo->existsTicket($number);
        echo json_encode(['ok' => true, 'duplicate' => $dup]);
    }

    /* ==========================================================
     * STORE (POST /audit-entries)
     * ========================================================== */

    public function store(): void
    {
        $post   = $_POST ?? [];
        $logger = $this->logger;

        /* ---------- Sobrescreve via kyndryl ---------- */
        if (!empty($_SESSION['user']['id'])) {
           
$userId = (int)$_SESSION['user']['id'];

$ref = $this->kyndrylRepo
    ->getInspectorAndLocationByUserId($userId);

if (!$ref) {
    $_SESSION['flash_error'] =
        'Seu perfil não está completamente configurado (fiscal ou localidade). 
         Entre em contato com o administrador.';
    $_SESSION['flash_old'] = $post;

    header('Location: ' . $this->base() . '/?invalid=profile', true, 303);
    exit;
}

$post['inspector_id'] = $ref['inspector_id'];
$post['location_id']            = $ref['location_id'];

        }

        /* ---------- Auditor ---------- */
        if (!empty($_SESSION['user']['id']) && !empty($_SESSION['user']['name'])) {
            $post['user_id']         = (int)$_SESSION['user']['id'];
            $post['kyndryl_auditor'] = (string)$_SESSION['user']['name'];
        }

        try {
            $id = $this->service->handle($post);

            $logger?->write('debug.log', date('c') . " OK id={$id}\n");

            header('Location: ' . $this->base() . '/?created=' . urlencode((string)$id), true, 303);
            exit;

        } catch (\PDOException $e) {

    // 🔎 SQLite: erro 19 = UNIQUE constraint violation
    $errorCode = $e->getCode();              // geralmente "23000"
    $errorInfo = $e->errorInfo[1] ?? null;   // SQLite = 19

    if ($errorInfo === 19 && str_contains($e->getMessage(), 'ticket_number')) {
        $ticket = (string)($post['ticket_number'] ?? '');

        $_SESSION['flash_error'] =
            $ticket !== ''
                ? "O ticket {$ticket} já está cadastrado."
                : "Este ticket já está cadastrado.";

        $_SESSION['flash_old'] = $post;

        header('Location: ' . $this->base() . '/?dupe=1', true, 303);
        exit;
    }

    // fallback genérico
   $_SESSION['flash_error'] =
    'Erro ao salvar o chamado: ' . $e->getMessage();

$_SESSION['flash_old'] = $post;

header('Location: ' . $this->base() . '/?invalid=debug', true, 303);
exit;
}

catch (\Throwable $e) {

    $_SESSION['flash_error'] = $e->getMessage();
    $_SESSION['flash_old']   = $post;

    header('Location: ' . $this->base() . '/?invalid=1', true, 303);
    exit;
}
    }

    /* ==========================================================
     * EXPORT CSV
     * ========================================================== */

    public function exportCsv(): void
{
    $userId = (int)($_SESSION['user']['id'] ?? 0);
    if ($userId <= 0) {
        http_response_code(401);
        echo "Não autenticado.";
        return;
    }

    // ✅ RECEBE O MÊS DA URL
    $month = isset($_GET['audit_month']) && $_GET['audit_month'] !== ''
        ? trim((string)$_GET['audit_month'])
        : null;

    // ✅ ENVIA O MÊS PARA O REPOSITORY
    $rows = $this->repo->exportRows([
        'user_id'     => $userId,
        'audit_month' => $month,
    ]);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="auditoria_chamados.csv"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");

    foreach ($rows as $r) {
        fputcsv($out, array_values($r), ';', '"', '\\');
    }

    fclose($out);
    exit;
    }
}
