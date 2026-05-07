<?php
declare(strict_types=1);

class ExecutionPlanController
{
    private ?PDO $pdo;

    public function __construct()
    {
        // 🔐 Restrição ADMIN
        if (($_SESSION['user']['tipo'] ?? 1) !== 0) {
            http_response_code(403);
            exit('Acesso restrito ao administrador.');
        }

        $this->pdo = $GLOBALS['pdo'] ?? null;
    }

    /**
     * Exibe o formulário
     */
    public function create(): void
    {
        require __DIR__ . '/../../../views/admin/execution_plans/create.php';
    }

    /**
     * Processa o POST (salva como rascunho)
     */
    public function store(): void
    {
        // ✅ Validação mínima
        $name    = trim($_POST['name'] ?? '');
        $version = trim($_POST['version'] ?? '');
        $type    = $_POST['audit_type'] ?? '';
        $summary = trim($_POST['normative_summary'] ?? '');

        $errors = [];

        if ($name === '')   $errors[] = 'Nome é obrigatório.';
        if ($version === '') $errors[] = 'Versão é obrigatória.';
        if (!in_array($type, ['estoque','chamados','ambos'], true))
            $errors[] = 'Tipo de auditoria inválido.';
        if (strlen($summary) < 50)
            $errors[] = 'Resumo normativo muito curto (mín. 50 caracteres).';

        if (!empty($errors)) {
            $_SESSION['form_errors'] = $errors;
            header('Location: /admin/execution-plans/create');
            return;
        }

        // ✅ Se ainda não migramos, apenas simulamos
        if ($this->pdo === null) {
            $_SESSION['flash'] =
                'Plano salvo apenas em modo simulado (migration ainda não executada).';
            header('Location: /admin/execution-plans');
            return;
        }

        // ✅ Inserção real
        $stmt = $this->pdo->prepare("
            INSERT INTO execution_plans (
                name, version, audit_type,
                status, normative_summary,
                created_by
            ) VALUES (
                :name, :version, :audit_type,
                'draft', :summary,
                :created_by
            )
        ");

        $stmt->execute([
            ':name' => $name,
            ':version' => $version,
            ':audit_type' => $type,
            ':summary' => $summary,
            ':created_by' => $_SESSION['user']['id']
        ]);

        $_SESSION['flash'] = 'Plano de Execução criado como rascunho.';
        header('Location: /admin/execution-plans');
    }
}