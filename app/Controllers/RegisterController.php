<?php
declare(strict_types=1);

namespace App\Controllers;

use PDO; // ✅ FALTAVA ISSO
use App\Repositories\LocationRepository;
use App\Repositories\PetrobrasInspectorRepository;
use App\Repositories\AllowedEmailRepository;

final class RegisterController
{

    
public function __construct(
    private LocationRepository            $locationRepo,
    private PetrobrasInspectorRepository  $inspectorRepo,
    private AllowedEmailRepository        $allowedEmailRepo,
    private PDO                          $pdo
) {}

private function base(): string
{
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    return rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
}


private function backWithError(string $message): void
{
    $_SESSION['register_error'] = $message;
    $_SESSION['register_old']   = $_POST; // ✅ preserva dados

    header('Location: ' . $this->base() . '/register');
    exit;
}
    /**
     * Exibe o formulário de cadastro
     */
    public function show(): void
{
    $locations  = $this->locationRepo->all();
    $inspectors = $this->inspectorRepo->all();

    // ✅ ler erro e dados antigos da sessão
    $error = $_SESSION['register_error'] ?? null;
    $old   = $_SESSION['register_old'] ?? [];

    // ✅ limpar após leitura
    unset($_SESSION['register_error'], $_SESSION['register_old']);

    $this->render('register', [
        'title'      => 'Cadastro',
        'locations'  => $locations,
        'inspectors' => $inspectors,
        'error'      => $error,
        'old'        => $old,
    ]);
}

    public function store(): void
{
    $email = strtolower(trim($_POST['email'] ?? ''));
    $inspectorId = (int)($_POST['inspector_id'] ?? 0);
    $locationId  = (int)($_POST['location_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    // validações básicas
    if (
        !$email || !$password || !$passwordConfirm ||
        $inspectorId <= 0 || $locationId <= 0
    ) {
        $this->backWithError('Preencha todos os campos.');
    }

    // email permitido
    $allowed = $this->allowedEmailRepo->findActiveByEmail($email);
    if (!$allowed) {
        $this->backWithError('Email não autorizado.');
    }

    // senha
    if ($password !== $passwordConfirm) {
        $this->backWithError('As senhas não conferem.');
    }

    if (!preg_match('/^(?=.*[A-Za-z])(?=.*\d)[A-Za-z\d]{6,15}$/', $password)) {
        $this->backWithError(
            'Senha deve ter entre 6 e 15 caracteres, com letras e números.'
        );
    }

    // verifica duplicidade
    $check = $this->pdo->prepare(
        "SELECT 1 FROM kyndryl_auditors WHERE email = :email"
    );
    $check->execute([':email' => $email]);
    if ($check->fetch()) {
        $this->backWithError('Email já cadastrado.');
    }

    // hash da senha ✅
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // insert
    $stmt = $this->pdo->prepare(
        "INSERT INTO kyndryl_auditors
         (kyndryl_auditor, email, password_hash, inspector_id, location_id, user_type)
         VALUES
         (:name, :email, :hash, :inspector, :location, 1)"
    );

    $stmt->execute([
        ':name'      => $allowed['name'],
        ':email'     => $email,
        ':hash'      => $hash,
        ':inspector' => $inspectorId,
        ':location'  => $locationId
    ]);

    
$_SESSION['register_success'] =
    '✅ Auditor cadastrado com sucesso! Agora você já pode realizar o login.';


    header('Location: ' . $this->base() . '/login?registered=1');
    exit;
}

    private function render(string $view, array $data = []): void
    {
        $baseDir = dirname(__DIR__) . '/Views';
        $layout  = $baseDir . '/layout.php';

        if (!empty($data)) {
            extract($data, EXTR_OVERWRITE);
        }

        require $layout;
    }
}