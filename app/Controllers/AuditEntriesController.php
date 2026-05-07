<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\CreateAuditEntryService;
use App\Repositories\AuditEntryRepository;
use App\Repositories\KyndrylAuditorRepository;
use App\Services\ExportAuditoriaMensal;
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

    /* ---------- Autopreenchimento ---------- */
    if (!empty($_SESSION['user']['id'])) {
        $userId = (int) $_SESSION['user']['id'];

        $ref = $this->kyndrylRepo->getInspectorAndLocationByUserId($userId);

        if (is_array($ref)) {

            if (!empty($ref['inspector_id']) && !empty($ref['petrobras_inspector'])) {
                $old['petrobras_inspector']    = (string)$ref['petrobras_inspector'];
                $old['petrobras_inspector_id'] = (int)$ref['inspector_id'];
                $old['_lock_inspector_field']  = 1;
            }

            if (!empty($ref['location_id']) && !empty($ref['location'])) {
                $old['location']             = (string)$ref['location'];
                $old['location_id']          = (int)$ref['location_id'];
                $old['_lock_location_field'] = 1;
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
    


    /* ==========================================================
     * API – VALIDATE TICKET
     * ========================================================== */

    public function validateTicket(): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $number = trim((string)($_GET['number'] ?? ''));

        if ($number === '' || !preg_match('/^(INC|RITM|REQ|SCTASK|TASK)\d+$/', $number)) {
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

    // ✅ NORMALIZA E VALIDA O TICKET
    $ticket = strtoupper(trim((string)($post['ticket_number'] ?? '')));

    if ($ticket === '') {
        throw new \RuntimeException('Número do ticket é obrigatório.');
    }

    if (!preg_match('/^(INC|RITM|REQ|SCTASK|TASK)\d+$/i', $ticket)) {
        throw new \RuntimeException('Tipo de Ticket inválido.');
    }

    // ✅ sobrescreve o valor normalizado
    $post['ticket_number'] = $ticket;

    /* ---------- Sobrescreve via kyndryl ---------- */
    if (!empty($_SESSION['user']['id'])) {

        $userId = (int) $_SESSION['user']['id'];

        $ref = $this->kyndrylRepo
            ->getInspectorAndLocationByUserId($userId);

        if (!$ref) {
            $_SESSION['flash_error'] =
                'Seu perfil não está completamente configurado (fiscal ou localidade).
                 Entre em contato com o administrador.';

            $_SESSION['flash_old'] = $post;

            header(
                'Location: ' . $this->base() . '/?invalid=profile',
                true,
                303
            );
            exit;
        }

        // força dados confiáveis
        $post['inspector_id'] = $ref['inspector_id'];
        $post['location_id']  = $ref['location_id'];
    }

    /* ---------- Auditor ---------- */
    if (!empty($_SESSION['user']['id']) && !empty($_SESSION['user']['name'])) {
        $post['user_id']         = (int) $_SESSION['user']['id'];
        $post['kyndryl_auditor'] = (string) $_SESSION['user']['name'];
    }

    try {
        $id = $this->service->handle($post);

        $logger?->write(
            'debug.log',
            date('c') . " OK id={$id}\n"
        );

        header(
            'Location: ' . $this->base() . '/?created=' . urlencode((string)$id),
            true,
            303
        );
        exit;

    } catch (\PDOException $e) {

        /**
         * ✅ TRATAMENTO DEFINITIVO PARA DUPLICIDADE (SQLite)
         *
         * Código SQLite:
         * - SQLSTATE: 23000
         * - errorInfo[1]: 19
         */
        $sqliteCode = $e->errorInfo[1] ?? null;

        if (
            $sqliteCode === 19 &&
            str_contains($e->getMessage(), 'ticket_number')
        ) {
            $ticket = strtoupper(
                trim((string)($post['ticket_number'] ?? ''))
            );

            $_SESSION['flash_error'] =
                $ticket !== ''
                    ? "O ticket {$ticket} já está cadastrado."
                    : "Este número de ticket já está cadastrado.";

            $_SESSION['flash_old'] = $post;

            header(
                'Location: ' . $this->base() . '/?dupe=1',
                true,
                303
            );
            exit;
        }

        // ❌ erro inesperado de banco
        $_SESSION['flash_error'] =
            'Chamado já registrado.';

        $_SESSION['flash_old'] = $post;

        header(
            'Location: ' . $this->base() . '/?invalid=1',
            true,
            303
        );
        exit;

    } catch (\Throwable $e) {

        // ❌ erro de validação / lógica
        $_SESSION['flash_error'] = $e->getMessage();
        $_SESSION['flash_old']   = $post;

        header(
            'Location: ' . $this->base() . '/?invalid=1',
            true,
            303
        );
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

    public function exportXlsx(): void
{


set_time_limit(0);
ini_set('memory_limit', '512M');

    $userId = (int)($_SESSION['user']['id'] ?? 0);

    if ($userId <= 0) {
        http_response_code(401);
        echo 'Não autenticado.';
        return;
    }

    // ✅ mês vindo da URL (MESMO PADRÃO DO CSV)
    $month = isset($_GET['audit_month']) && $_GET['audit_month'] !== ''
        ? trim((string)$_GET['audit_month'])
        : null;

    if (!$month) {
        throw new \RuntimeException('Mês não informado.');
    }

    // ✅ reutiliza o Repository que JÁ EXISTE no controller
    $exportService = new ExportAuditoriaMensal($this->repo);

    // ✅ gera o arquivo
    $arquivo = $exportService->exportar($month);

    // ✅ força download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . basename($arquivo) . '"');
    header('Content-Length: ' . filesize($arquivo));

    readfile($arquivo);
    exit;
}

    public function import(): void
{
    if (empty($_FILES['file']['tmp_name'])) {
        $_SESSION['flash_error'] = 'Nenhum arquivo enviado.';
        header('Location: /import');
        exit;
    }

    try {
        $this->service->importCsv(
            $_FILES['file']['tmp_name'],
            $_SESSION['user'],
            $_FILES['file']['name']
        );

        $_SESSION['flash_success'] = 'Importação realizada com sucesso.';
    } catch (\Throwable $e) {
        $_SESSION['flash_error'] = $e->getMessage();
    }

    header('Location: /import');
    exit;
}

public function noncomplianceStats(): void
{
    $userId = (int)($_SESSION['user']['id'] ?? 0);
    if ($userId <= 0) {
        header('Location: ' . $this->base() . '/login');
        exit;
    }

    // ✅ Filtros
    $month = isset($_GET['month']) && $_GET['month'] !== ''
        ? trim((string)$_GET['month'])
        : null;

    $resolverGroup = isset($_GET['resolver_group']) && $_GET['resolver_group'] !== ''
        ? trim((string)$_GET['resolver_group'])
        : null;

    // ✅ Listas para os selects
    $months = $this->repo->listAuditMonthsByUser($userId);

    if (method_exists($this->repo, 'listResolverGroupsByUser')) {
        $resolverGroups = $this->repo->listResolverGroupsByUser($userId);
    } else {
        $resolverGroups = [];
    }

    // ✅ TOTAL DE CHAMADOS DO CENÁRIO (BASE DO FILTRO)
    $totals = $this->repo->countAuditsByMonth($userId, $month);

    $totalAudits  = (int)($totals['total'] ?? 0);
    $totalNC      = (int)($totals['noncompliant'] ?? 0);
    $totalOK      = max(0, $totalAudits - $totalNC);

    $scenarioTotals = [
        'total'         => $totalAudits,
        'noncompliant'  => $totalNC,
        'compliant'     => $totalOK,
    ];

    // ✅ Buscar não conformidades
    $statsByResolver = [];

    $rows = $this->repo->fetchNoncomplianceGroupedByResolver(
        $userId,
        $month,
        $resolverGroup
    );

    foreach ($rows as $row) {
        $resolver = $row['resolver_group'] ?: 'Sem Mesa';
        $raw = trim((string)($row['noncompliance_reasons'] ?? ''));

        if ($raw === '') continue;

        foreach (array_map('trim', explode(';', $raw)) as $label) {
            if ($label === '') continue;
            $statsByResolver[$resolver][$label] =
                ($statsByResolver[$resolver][$label] ?? 0) + 1;
        }
    }

    // ✅ Consolida quando Mesa = Todas
    if ($resolverGroup === null && !empty($statsByResolver)) {
        $all = [];
        foreach ($statsByResolver as $stats) {
            foreach ($stats as $label => $count) {
                $all[$label] = ($all[$label] ?? 0) + $count;
            }
        }
        $statsByResolver = ['Todas as Mesas' => $all];
    }

    // ✅ Gráfico percentual geral (Resumo)
    $complianceSummary = null;

    if ($resolverGroup === null && $totalAudits > 0) {
        $complianceSummary = [
            'labels' => ['Em Conformidade', 'Não Conformidade'],
            'values' => [
                round(($totalOK / $totalAudits) * 100, 1),
                round(($totalNC / $totalAudits) * 100, 1),
            ],
        ];
    }

    // ✅ Render
    $this->render('noncompliance_stats', [
        'title'                 => 'Estatísticas de Não Conformidades',
        'statsByResolver'       => $statsByResolver,
        'months'                => $months,
        'resolverGroups'        => $resolverGroups,
        'selectedMonth'         => $month,
        'selectedResolverGroup' => $resolverGroup,
        'complianceSummary'     => $complianceSummary,
        'scenarioTotals'        => $scenarioTotals,
    ]);
}

public function exportScenarioHtml(): void
{
    $userId = (int)($_SESSION['user']['id'] ?? 0);
    if ($userId <= 0) {
        http_response_code(401);
        exit('Não autenticado');
    }

    // filtros
    $month = isset($_GET['month']) && $_GET['month'] !== '' ? trim($_GET['month']) : null;
    $resolverGroup = isset($_GET['resolver_group']) && $_GET['resolver_group'] !== '' ? trim($_GET['resolver_group']) : null;

    // reutiliza A MESMA LÓGICA do relatório
    $statsByResolver = [];

    $rows = $this->repo->fetchNoncomplianceGroupedByResolver(
        $userId,
        $month,
        $resolverGroup
    );

    foreach ($rows as $row) {
        $resolver = $row['resolver_group'] ?: 'Sem Mesa';
        $raw = trim((string)($row['noncompliance_reasons'] ?? ''));
        if ($raw === '') continue;

        foreach (array_map('trim', explode(';', $raw)) as $label) {
            if ($label === '') continue;
            $statsByResolver[$resolver][$label] =
                ($statsByResolver[$resolver][$label] ?? 0) + 1;
        }
    }

    if ($resolverGroup === null && !empty($statsByResolver)) {
        $all = [];
        foreach ($statsByResolver as $stats) {
            foreach ($stats as $k => $v) {
                $all[$k] = ($all[$k] ?? 0) + $v;
            }
        }
        $statsByResolver = ['Todas as Mesas' => $all];
    }

    // gera HTML puro
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="cenario_' . ($month ?? 'todos') . '.html"');

    // ✅ TOTAL DE CHAMADOS DO CENÁRIO (MESMA REGRA DA TELA)
$totals = $this->repo->countAuditsByMonth($userId, $month);

$totalAudits = (int)($totals['total'] ?? 0);
$totalNC     = (int)($totals['noncompliant'] ?? 0);
$totalOK     = max(0, $totalAudits - $totalNC);

$scenarioTotals = [
    'total'         => $totalAudits,
    'noncompliant'  => $totalNC,
    'compliant'     => $totalOK,
];

    include dirname(__DIR__) . '/Views/noncompliance_export.php';
    exit;
}
}
