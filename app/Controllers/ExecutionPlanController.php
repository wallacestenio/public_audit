<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\ExecutionPlanRepository;

class ExecutionPlanController
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }


    /**
     * Lista planos de execução
     */
    public function index(): void
    {
        $plans = ExecutionPlanRepository::listAll($this->pdo);
        require __DIR__ . '/../Views/admin/execution_plans/index.php';
    }

    /**
     * Exibe formulário de criação
     */
    public function create(): void
    {
        require __DIR__ . '/../Views/admin/execution_plans/create.php';
    }

    /**
     * Salva novo plano como rascunho (com PDF opcional)
     */
    public function store(): void
    {
        $name    = trim($_POST['name'] ?? '');
        $version = trim($_POST['version'] ?? '');
        $type    = $_POST['audit_type'] ?? '';
        $summary = trim($_POST['normative_summary'] ?? '');

        if ($name === '' || $version === '' || $summary === '') {
            $_SESSION['form_errors'] = ['Campos obrigatórios não preenchidos.'];
            header('Location: /execution-plans/create');
            exit;
        }

        // =====================================================
        // PDF (opcional) – validação e preparação
        // =====================================================
        $pdfPath = null;
        $pdfHash = null;

        if (!empty($_FILES['execution_plan_pdf']['tmp_name'])) {

            if ($_FILES['execution_plan_pdf']['error'] !== UPLOAD_ERR_OK) {
                $_SESSION['form_errors'] = ['Erro ao enviar o PDF.'];
                header('Location: /execution-plans/create');
                exit;
            }

            if ($_FILES['execution_plan_pdf']['type'] !== 'application/pdf') {
                $_SESSION['form_errors'] = ['O arquivo anexado deve ser um PDF.'];
                header('Location: /execution-plans/create');
                exit;
            }

            if ($_FILES['execution_plan_pdf']['size'] > 10 * 1024 * 1024) {
                $_SESSION['form_errors'] = ['O PDF excede o tamanho máximo permitido (10MB).'];
                header('Location: /execution-plans/create');
                exit;
            }

            // Caminho físico (ajuste se quiser mudar)
            $storageDir = __DIR__ . '/../../storage/execution_plans';
            if (!is_dir($storageDir)) {
                mkdir($storageDir, 0775, true);
            }

            $filename = uniqid('pe_', true) . '.pdf';
            $destination = $storageDir . '/' . $filename;

            if (!move_uploaded_file($_FILES['execution_plan_pdf']['tmp_name'], $destination)) {
                $_SESSION['form_errors'] = ['Falha ao salvar o PDF.'];
                header('Location: /execution-plans/create');
                exit;
            }

            $pdfPath = 'storage/execution_plans/' . $filename;
            $pdfHash = hash_file('sha256', $destination);
        }

        // =====================================================
        // Persistência (via Repository)
        // =====================================================
        ExecutionPlanRepository::createDraft(
            $this->pdo,
            $name,
            $version,
            $type,
            $summary,
            (int)$_SESSION['user']['id'],
            $pdfPath,
            $pdfHash
        );

        header('Location: /execution-plans');
        exit;
    }

    /**
 * Download do PDF do Plano de Execução
 */
public function downloadPdf(int $id): void
{
    $plan = ExecutionPlanRepository::findById($this->pdo, $id);

    if (!$plan || empty($plan['pdf_path'])) {
        http_response_code(404);
        exit('Documento PDF não encontrado.');
    }

    $filePath = __DIR__ . '/../../' . $plan['pdf_path'];

    if (!is_file($filePath)) {
        http_response_code(404);
        exit('Arquivo não disponível.');
    }

    $fileName = basename($filePath);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: private');

    readfile($filePath);
    exit;
}

/* 
ATIVA PLANO DE EXECUÇÃO (AJUSTA STATUS E DATA DE ATIVAÇÃO);
👉 Ativa o plano selecionado
👉 Desativa todos os outros
👉 Redireciona depois

*/

public function activate(int $id): void
{


echo "Ativando plano: " . $id;
    exit;

    $stmt = $this->pdo->prepare("
        UPDATE execution_plans
        SET is_active = CASE WHEN id = :id THEN 1 ELSE 0 END
    ");

    $stmt->execute(['id' => $id]);

    header('Location: /execution-plans');
    exit;
}
``

/* ACTIVE PLANO DE EXECUÇÃO (AJUSTA STATUS E DATA DE ATIVAÇÃO) */
public function activate(int $id): void
{
    // Desativa qualquer plano ativo
    $this->pdo->exec("
        UPDATE execution_plans
        SET status = 'draft',
            activated_at = NULL
        WHERE status = 'active'
    ");

    // Ativa o plano selecionado
    $stmt = $this->pdo->prepare("
        UPDATE execution_plans
        SET status = 'active',
            activated_at = datetime('now')
        WHERE id = :id
    ");

    $stmt->execute(['id' => $id]);

    header('Location: /execution-plans');
    exit;
}



}
