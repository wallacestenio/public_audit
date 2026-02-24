<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Support\Auth;

final class LoginController
{
    public function __construct(private Auth $auth) {}

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

    /** GET /login */
    public function show(): void
    {
        if ($this->auth->check()) {
            header('Location: ' . $this->base() . '/');
            exit;
        }
        $this->render('login', [
            'title' => 'Entrar',
            'error' => null,
        ]);
    }

    /** POST /login */
    public function login(): void
    {
        $base = $this->base();
        $u = (string)($_POST['username'] ?? '');
        $p = (string)($_POST['password'] ?? '');

        // Toda a lógica de autenticação fica no Auth
        if ($this->auth->login($u, $p)) {
            header('Location: ' . $base . '/');
            exit;
        }

        // Falha → renderiza com erro
        http_response_code(401);
        $this->render('login', [
            'title' => 'Entrar',
            'error' => 'Usuário ou senha inválidos.',
        ]);
    }

    /** POST /logout */
    public function logout(): void
    {
        $this->auth->logout();
        header('Location: ' . $this->base() . '/login');
        exit;
    }
}